<?php

namespace App\Services\Checkout;

use App\Enums\DeliveryMethod;
use App\Models\PromoCode;
use App\Services\Cart\CartLine;
use App\Services\Cdek\CdekRate;
use App\Services\Cdek\PickupPoint;
use Illuminate\Support\Collection;

/**
 * What the order costs right now: goods, promo discount, delivery, total.
 * Always worked out on the server from the cart; the browser only shows it.
 */
class Quote
{
    /**
     * @param  Collection<string, CartLine>  $lines
     */
    public function __construct(
        public readonly Collection $lines,
        public readonly float $subtotal,
        public readonly ?PromoCode $promo,
        public readonly float $discount,
        public readonly DeliveryMethod $deliveryMethod,
        public readonly float $deliveryPrice,
        public readonly ?float $remainingForFreeDelivery,
        public readonly ?float $freeDeliveryFrom = null,
        public readonly ?PickupPoint $pickupPoint = null,
        public readonly ?CdekRate $rate = null,
        // Why the delivery price is not known yet, e.g. no pickup point picked.
        public readonly ?string $deliveryProblem = null,
    ) {}

    /**
     * Whether the delivery price is known, so the order can be placed.
     */
    public function deliveryReady(): bool
    {
        return $this->deliveryProblem === null;
    }

    public function count(): int
    {
        return $this->lines->sum('quantity');
    }

    /**
     * The goods after the discount; free delivery is judged on this.
     */
    public function goods(): float
    {
        return $this->subtotal - $this->discount;
    }

    public function total(): float
    {
        return $this->goods() + $this->deliveryPrice;
    }
}
