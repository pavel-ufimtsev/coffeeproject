<?php

namespace App\Services\Checkout;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Models\PromoCode;
use App\Services\Cart\Cart;
use App\Services\Cdek\CdekException;
use Illuminate\Contracts\Session\Session;

/**
 * The checkout in progress: the cart, the promo code entered and what it all
 * costs. The promo code is kept in the session next to the cart and checked
 * again every time it is used.
 */
class Checkout
{
    public const PROMO_KEY = 'checkout.promo';

    public function __construct(
        private readonly Cart $cart,
        private readonly CourierRates $courier,
        private readonly CdekDelivery $cdek,
        private readonly Session $session,
    ) {}

    public function cart(): Cart
    {
        return $this->cart;
    }

    public function courier(): CourierRates
    {
        return $this->courier;
    }

    public function cdek(): CdekDelivery
    {
        return $this->cdek;
    }

    /**
     * Delivery methods customers can pick now. CDEK orders are paid by card
     * online, so CDEK needs T-Bank switched on as well.
     *
     * @return list<DeliveryMethod>
     */
    public function deliveryMethods(): array
    {
        return array_values(array_filter([
            $this->courier->enabled() ? DeliveryMethod::Courier : null,
            $this->cdek->enabled() && PaymentMethod::TBank->isEnabled() ? DeliveryMethod::Cdek : null,
        ]));
    }

    /**
     * Payment methods for the delivery picked: CDEK orders are paid online only.
     *
     * @return list<PaymentMethod>
     */
    public function paymentMethods(?DeliveryMethod $delivery = null): array
    {
        $methods = PaymentMethod::enabled();

        return $delivery === DeliveryMethod::Cdek
            ? array_values(array_filter($methods, fn (PaymentMethod $method) => $method->isOnline()))
            : $methods;
    }

    /**
     * Whether an order can be placed at all: something to buy, a way to get it and a way to pay.
     */
    public function isOpen(): bool
    {
        return ! $this->cart->isEmpty() && $this->deliveryMethods() !== [] && $this->paymentMethods() !== [];
    }

    /**
     * Apply a promo code. Returns why it cannot be used, or null on success.
     */
    public function applyPromo(string $code): ?string
    {
        $promo = PromoCode::findByCode($code);

        if ($promo === null) {
            return __('There is no such promo code.');
        }

        if ($problem = $promo->problemFor($this->cart->subtotal())) {
            return $problem;
        }

        $this->session->put(self::PROMO_KEY, $promo->code);

        return null;
    }

    public function removePromo(): void
    {
        $this->session->forget(self::PROMO_KEY);
    }

    /**
     * The code entered, if any, whether or not it still works.
     */
    public function promoCode(): ?string
    {
        return $this->session->get(self::PROMO_KEY);
    }

    /**
     * Why the entered code no longer works (expired, cart too small), or null.
     */
    public function promoProblem(): ?string
    {
        if ($this->promoCode() === null) {
            return null;
        }

        $promo = PromoCode::findByCode($this->promoCode());

        return $promo === null ? (string) __('There is no such promo code.') : $promo->problemFor($this->cart->subtotal());
    }

    /**
     * @param  string|null  $pickupPoint  The CDEK point code picked on the map
     */
    public function quote(DeliveryMethod $method = DeliveryMethod::Courier, ?PromoCode $promo = null, ?string $pickupPoint = null): Quote
    {
        $subtotal = $this->cart->subtotal();
        $promo ??= $this->usablePromo($subtotal);
        $discount = $promo?->discountFor($subtotal) ?? 0.0;
        $goods = $subtotal - $discount;

        // The goods part is the same for every method; $delivery fills in the rest.
        $quote = fn (array $delivery) => new Quote(...[
            'lines' => $this->cart->lines(),
            'subtotal' => $subtotal,
            'promo' => $promo,
            'discount' => $discount,
            'deliveryMethod' => $method,
            ...$delivery,
        ]);

        if ($method === DeliveryMethod::Courier) {
            return $quote([
                'deliveryPrice' => $this->courier->cost($goods),
                'remainingForFreeDelivery' => $this->courier->remainingForFree($goods),
                'freeDeliveryFrom' => $this->courier->price() > 0 ? $this->courier->freeFrom() : null,
            ]);
        }

        $cdek = [
            'deliveryPrice' => 0.0,
            'remainingForFreeDelivery' => $this->cdek->remainingForFree($goods),
            'freeDeliveryFrom' => $this->cdek->freeFrom(),
        ];

        if (blank($pickupPoint)) {
            return $quote([...$cdek, 'deliveryProblem' => __('Choose a pickup point on the map.')]);
        }

        try {
            $point = $this->cdek->pickupPoint($pickupPoint);

            if ($point === null) {
                return $quote([...$cdek, 'deliveryProblem' => __('This pickup point is no longer available. Please choose another one.')]);
            }

            $rate = $this->cdek->rate($point, $this->cart);
        } catch (CdekException $e) {
            report($e);

            return $quote([...$cdek, 'deliveryProblem' => __('We could not get the CDEK price right now. Please try again in a minute.')]);
        }

        return $quote([...$cdek, 'deliveryPrice' => $this->cdek->cost($goods, $rate), 'pickupPoint' => $point, 'rate' => $rate]);
    }

    /**
     * Forget the promo code and cart once the order is placed.
     */
    public function finish(): void
    {
        $this->removePromo();
        $this->cart->clear();
    }

    private function usablePromo(float $subtotal): ?PromoCode
    {
        if ($this->promoCode() === null) {
            return null;
        }

        $promo = PromoCode::findByCode($this->promoCode());

        return $promo !== null && $promo->problemFor($subtotal) === null ? $promo : null;
    }
}
