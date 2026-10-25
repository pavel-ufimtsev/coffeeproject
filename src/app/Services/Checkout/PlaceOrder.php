<?php

namespace App\Services\Checkout;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\Address;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Cart\CartLine;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns the cart into an order: prices worked out again on the server (for
 * CDEK, asked from CDEK again), the promo code locked and counted, the
 * account holder's address saved, then the cart emptied. Cash orders are confirmed right away (email, retailCRM);
 * card orders wait for the bank, see CardPayment.
 */
class PlaceOrder
{
    public function __construct(
        private readonly Checkout $checkout,
        private readonly ConfirmOrder $confirm,
    ) {}

    /**
     * @throws ValidationException when the promo code stopped working or the total changed
     */
    public function handle(CheckoutRequest $request): Order
    {
        $token = $request->string('checkout_token')->toString();

        // A second submit of the same page lands on the order the first one placed.
        if ($existing = Order::firstWhere('checkout_token', $token)) {
            return $existing;
        }

        try {
            $order = DB::transaction(fn () => $this->create($request, $token));
        } catch (UniqueConstraintViolationException) {
            return Order::where('checkout_token', $token)->firstOrFail();
        }

        $this->checkout->finish();

        // Card orders are confirmed once the bank says they are paid.
        if (! $order->payment_method->isOnline()) {
            $this->confirm->handle($order);
        }

        return $order;
    }

    private function create(CheckoutRequest $request, string $token): Order
    {
        $method = DeliveryMethod::from($request->input('delivery_method'));
        $quote = $this->checkout->quote($method, pickupPoint: $request->isPickup() ? $request->string('pickup_point')->toString() : null);

        if (! $quote->deliveryReady()) {
            throw ValidationException::withMessages(['pickup_point' => $quote->deliveryProblem]);
        }

        // Lock the code so two orders cannot both take its last use.
        $promo = $quote->promo ? PromoCode::lockForUpdate()->find($quote->promo->id) : null;

        if ($quote->promo && ($promo === null || $promo->problemFor($quote->subtotal) !== null)) {
            $this->checkout->removePromo();

            throw ValidationException::withMessages(['promo' => $promo?->problemFor($quote->subtotal) ?? __('There is no such promo code.')]);
        }

        if ($request->filled('expected_total') && abs((float) $request->input('expected_total') - $quote->total()) >= 0.01) {
            throw ValidationException::withMessages(['total' => __('The order total has changed to :total. Please check your order and place it again.', ['total' => Money::rub($quote->total())])]);
        }

        $user = $request->user();

        $order = Order::create([
            'token' => Str::random(40),
            'checkout_token' => $token,
            'user_id' => $user?->id,
            'status' => OrderStatus::New,
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'phone' => $request->phone(),
            'email' => $request->input('email'),
            'delivery_method' => $method,
            'delivery_price' => $quote->deliveryPrice,
            ...($quote->pickupPoint ? $this->pickupPointColumns($quote) : $request->only(Order::ADDRESS_FIELDS)),
            'payment_method' => PaymentMethod::from($request->input('payment_method')),
            'promo_code_id' => $promo?->id,
            'promo_code' => $promo?->code,
            'subtotal' => $quote->subtotal,
            'discount' => $quote->discount,
            'total' => $quote->total(),
            'comment' => $request->input('comment'),
            'consent_at' => now(),
            'ip' => $request->ip(),
        ]);

        $order->update(['number' => 'CP-'.(10000 + $order->id)]);

        $order->items()->createMany($quote->lines->values()->map(fn (CartLine $line) => [
            'product_id' => $line->product()->id,
            'product_variant_id' => $line->variant->id,
            'retailcrm_offer_id' => $line->variant->retailcrm_offer_id,
            'name' => $line->product()->name,
            'variant_label' => $line->variant->label,
            'grind' => $line->grind,
            'grind_label' => $line->grindLabel(),
            'price' => $line->price(),
            'quantity' => $line->quantity,
            'total' => $line->total(),
        ])->all());

        $promo?->increment('used_count');

        if ($user && ! $request->isPickup()) {
            $this->saveAddress($user, $request);
        }

        return $order;
    }

    /**
     * Where a CDEK order goes: the point's city and address stand in for the street address.
     *
     * @return array<string, mixed>
     */
    private function pickupPointColumns(Quote $quote): array
    {
        return [
            'city' => $quote->pickupPoint->city,
            'street' => $quote->pickupPoint->address,
            'pickup_point_code' => $quote->pickupPoint->code,
            'pickup_point_name' => $quote->pickupPoint->name,
            'delivery_tariff' => $quote->rate?->tariff,
            'delivery_days' => $quote->rate?->days(),
        ];
    }

    /**
     * Save edits back to the address picked, or add the new one.
     */
    private function saveAddress(User $user, CheckoutRequest $request): void
    {
        $fields = $request->only(Address::ADDRESS_FIELDS);

        if (! $request->wantsNewAddress()) {
            $user->addresses()->findOrFail($request->integer('address_id'))->update($fields);

            return;
        }

        $user->addresses()->create([...$fields, 'label' => $request->input('address_label')]);
    }
}
