<?php

namespace App\Services\Checkout;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Models\PromoCode;
use App\Services\Cart\Cart;
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

    /**
     * Delivery methods customers can pick now. CDEK is shown as coming soon.
     *
     * @return list<DeliveryMethod>
     */
    public function deliveryMethods(): array
    {
        return $this->courier->enabled() ? [DeliveryMethod::Courier] : [];
    }

    /**
     * @return list<PaymentMethod>
     */
    public function paymentMethods(): array
    {
        return PaymentMethod::enabled();
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

        return $promo === null ? __('There is no such promo code.') : $promo->problemFor($this->cart->subtotal());
    }

    public function quote(DeliveryMethod $method = DeliveryMethod::Courier, ?PromoCode $promo = null): Quote
    {
        $subtotal = $this->cart->subtotal();
        $promo ??= $this->usablePromo($subtotal);
        $discount = $promo?->discountFor($subtotal) ?? 0.0;
        $goods = $subtotal - $discount;

        return new Quote(
            lines: $this->cart->lines(),
            subtotal: $subtotal,
            promo: $promo,
            discount: $discount,
            deliveryMethod: $method,
            deliveryPrice: $this->courier->cost($goods),
            remainingForFreeDelivery: $this->courier->remainingForFree($goods),
        );
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
