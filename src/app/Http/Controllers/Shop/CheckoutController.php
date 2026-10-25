<?php

namespace App\Http\Controllers\Shop;

use App\Enums\DeliveryMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\Checkout;
use App\Services\Checkout\PlaceOrder;
use App\Services\TBank\CardPayment;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * One-page checkout: contacts, delivery, payment, then the thank-you page.
 * The summary (lines, promo code, totals) is redrawn in the background when
 * the promo code or delivery method changes; without JavaScript the promo
 * form posts here and comes back to the page.
 */
class CheckoutController extends Controller
{
    public function show(Request $request, Checkout $checkout): View|RedirectResponse
    {
        if ($checkout->cart()->isEmpty()) {
            return to_route('cart.show');
        }

        $user = $request->user();
        $addresses = $user?->addresses()->get() ?? collect();
        $selected = old('address_id', $request->query('address', $addresses->firstWhere('is_default', true)->id ?? 'new'));

        // Back after a validation error: keep the delivery and pickup point picked.
        $oldMethod = old('delivery_method');
        $oldPoint = old('pickup_point');
        $method = is_string($oldMethod) ? DeliveryMethod::tryFrom($oldMethod) : null;
        $method = in_array($method, $checkout->deliveryMethods(), true) ? $method : ($checkout->deliveryMethods()[0] ?? DeliveryMethod::Courier);

        return view('shop.checkout.show', [
            'checkout' => $checkout,
            'quote' => $checkout->quote($method, pickupPoint: is_string($oldPoint) ? $oldPoint : null),
            // New on every page load: placing an order consumes it, so a double submit places one order.
            'checkoutToken' => Str::random(40),
            'contact' => $this->contactDefaults($user),
            'addresses' => $addresses,
            'selectedAddress' => $addresses->firstWhere('id', (int) $selected),
            'isNewAddress' => ! $addresses->contains('id', (int) $selected),
        ]);
    }

    /**
     * The redrawn summary and delivery options, e.g. after switching the delivery method.
     */
    public function summary(Request $request, Checkout $checkout): JsonResponse
    {
        return $this->summaryResponse($checkout, $request);
    }

    public function applyPromo(Request $request, Checkout $checkout): JsonResponse|RedirectResponse
    {
        $request->validate(['promo' => ['required', 'string', 'max:50']], ['promo.required' => __('Enter a promo code.')]);

        $error = $checkout->applyPromo($request->string('promo')->toString());

        if (! $request->wantsJson()) {
            return $error ? back()->withErrors(['promo' => $error], 'promo')->withInput() : back();
        }

        return $this->summaryResponse($checkout, $request, $error);
    }

    public function removePromo(Request $request, Checkout $checkout): JsonResponse|RedirectResponse
    {
        $checkout->removePromo();

        return $request->wantsJson() ? $this->summaryResponse($checkout, $request) : back();
    }

    public function store(CheckoutRequest $request, Checkout $checkout, PlaceOrder $placeOrder, CardPayment $cardPayment): SymfonyResponse
    {
        if (! $checkout->isOpen()) {
            // The cart is empty after a successful submit; a repeat lands on that order.
            $placed = Order::firstWhere('checkout_token', $request->input('checkout_token'));

            return $placed ? to_route('checkout.complete', $placed->token) : to_route('cart.show');
        }

        $order = $placeOrder->handle($request);

        return $order->awaitsPayment()
            ? PaymentController::pay($order, $cardPayment)
            : to_route('checkout.complete', $order->token);
    }

    public function complete(Request $request, Order $order): View
    {
        return view('shop.checkout.complete', [
            'order' => $order->load('items.product.mainImage'),
            'hasAccount' => $request->user() !== null || User::where('email', $order->email)->exists(),
        ]);
    }

    /**
     * "Log in" on the checkout page: come back to checkout afterwards.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->session()->put('url.intended', route('checkout.show'));

        return to_route('login');
    }

    private function summaryResponse(Checkout $checkout, Request $request, ?string $promoError = null): JsonResponse
    {
        $quote = $checkout->quote($this->deliveryMethod($request), pickupPoint: $request->string('pickup_point')->toString() ?: null);

        return response()->json([
            'total' => $quote->total(),
            'totalLabel' => Money::rub($quote->total()),
            'deliveryReady' => $quote->deliveryReady(),
            'summary' => view('shop.checkout.summary', ['checkout' => $checkout, 'quote' => $quote, 'promoError' => $promoError])->render(),
            'delivery' => view('shop.checkout.delivery-options', ['checkout' => $checkout, 'quote' => $quote])->render(),
        ]);
    }

    private function deliveryMethod(Request $request): DeliveryMethod
    {
        return DeliveryMethod::tryFrom((string) $request->input('delivery')) ?? DeliveryMethod::Courier;
    }

    /**
     * Contacts for an account holder: from their last order, else their account.
     *
     * @return array{first_name: string, last_name: string, phone: string, email: string}
     */
    private function contactDefaults(?User $user): array
    {
        if ($user === null) {
            return ['first_name' => '', 'last_name' => '', 'phone' => '', 'email' => ''];
        }

        $last = $user->orders()->latest('id')->first();
        [$first, $rest] = array_pad(explode(' ', trim($user->name), 2), 2, '');

        return [
            'first_name' => $last->first_name ?? $first,
            'last_name' => $last->last_name ?? $rest,
            'phone' => $last ? Phone::format($last->phone) : '',
            'email' => $user->email,
        ];
    }
}
