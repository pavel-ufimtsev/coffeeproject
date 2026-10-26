<?php

namespace App\Services\Checkout;

use App\Services\Cdek\Cdek;
use App\Services\Cdek\CdekException;
use App\Services\Cdek\CdekRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * What CDEK delivery to a city costs and takes, for the delivery pages:
 * one 250 g pack to a pickup point, and how many points the city has.
 * Kept for a day and served stale for a week while it refreshes, so the
 * pages never wait on CDEK twice.
 */
class DeliveryEstimates
{
    /** The pack the pages quote: one 250 g bag of coffee. */
    public const PACK_GRAMS = 250;

    public function __construct(
        private readonly Cdek $cdek,
        private readonly CdekDelivery $delivery,
    ) {}

    /**
     * The price and days of a 250 g pack to a pickup point in the city;
     * null when CDEK is off or did not answer.
     */
    public function pickup(int $cityCode): ?CdekRate
    {
        if (! $this->delivery->enabled()) {
            return null;
        }

        $parcel = ['weight' => self::PACK_GRAMS + CdekDelivery::PACKAGING_WEIGHT, ...$this->delivery->box()];
        $from = (int) config('services.cdek.from_city_code');

        $rate = $this->remember("delivery.estimate.{$from}.{$cityCode}.".implode('x', $parcel), function () use ($from, $cityCode, $parcel) {
            $rate = $this->cdek->rate(CdekDelivery::TARIFF_PICKUP_POINT, $from, $cityCode, $parcel);

            return ['price' => $rate->price, 'min' => $rate->daysMin, 'max' => $rate->daysMax];
        });

        return $rate === null ? null : new CdekRate(CdekDelivery::TARIFF_PICKUP_POINT, (float) $rate['price'], (int) $rate['min'], (int) $rate['max']);
    }

    /**
     * How many CDEK pickup points and lockers the city has; null when unknown.
     */
    public function pointCount(int $cityCode): ?int
    {
        if (! $this->delivery->enabled()) {
            return null;
        }

        return $this->remember("delivery.points.{$cityCode}", fn () => count((array) $this->cdek->get('deliverypoints', ['city_code' => $cityCode])->json()));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function remember(string $key, callable $callback): mixed
    {
        try {
            return Cache::flexible($key, [now()->addDay(), now()->addWeek()], $callback);
        } catch (CdekException $e) {
            Log::warning('Delivery estimate unavailable: '.$e->getMessage());

            return null;
        }
    }
}
