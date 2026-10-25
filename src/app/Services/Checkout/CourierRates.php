<?php

namespace App\Services\Checkout;

use App\Models\Setting;

/**
 * Courier delivery in Ekaterinburg: a flat price, free from a set amount.
 * Both are edited in the admin (Delivery).
 */
class CourierRates
{
    public const DEFAULT_PRICE = 500;

    public const DEFAULT_FREE_FROM = 3000;

    public function enabled(): bool
    {
        return (bool) Setting::get('courier.enabled', true);
    }

    public function price(): float
    {
        return (float) Setting::get('courier.price', self::DEFAULT_PRICE);
    }

    /**
     * The goods total from which delivery is free; null when it is never free.
     */
    public function freeFrom(): ?float
    {
        $value = Setting::get('courier.free_from', self::DEFAULT_FREE_FROM);

        return $value === null ? null : (float) $value;
    }

    /**
     * The delivery price for goods worth $goods (after any promo discount).
     */
    public function cost(float $goods): float
    {
        return $this->freeFrom() !== null && $goods >= $this->freeFrom() ? 0.0 : $this->price();
    }

    /**
     * How much more to add for free delivery; null when it is free already or never.
     */
    public function remainingForFree(float $goods): ?float
    {
        if ($this->freeFrom() === null || $this->price() <= 0 || $goods >= $this->freeFrom()) {
            return null;
        }

        return $this->freeFrom() - $goods;
    }
}
