<?php

namespace App\Services\Checkout;

use App\Models\Setting;
use App\Services\Cart\Cart;
use App\Services\Cart\CartLine;
use App\Services\Cdek\Cdek;
use App\Services\Cdek\CdekRate;
use App\Services\Cdek\PickupPoint;
use Illuminate\Support\Facades\Cache;

/**
 * CDEK to a pickup point or parcel locker anywhere in Russia. Parcels are
 * dropped at a CDEK office in Ekaterinburg, so the tariffs are "склад-склад"
 * and "склад-постамат". The price is always asked from CDEK on the server;
 * the map only shows it. On/off, the free threshold and the box size are
 * edited in the admin (Delivery).
 */
class CdekDelivery
{
    /** Посылка склад-склад: office to pickup point. */
    public const TARIFF_PICKUP_POINT = 136;

    /** Посылка склад-постамат: office to parcel locker. */
    public const TARIFF_LOCKER = 368;

    /** Weight of a variant with none set, in grams. */
    public const DEFAULT_ITEM_WEIGHT = 300;

    /** The box, filler and label, in grams. */
    public const PACKAGING_WEIGHT = 150;

    public const DEFAULT_BOX = ['length' => 30, 'width' => 20, 'height' => 12];

    public function __construct(private readonly Cdek $cdek) {}

    /**
     * Switched on in the admin and the CDEK account is filled in.
     */
    public function enabled(): bool
    {
        return (bool) Setting::get('cdek.enabled', true) && $this->cdek->isConfigured() && filled(config('services.cdek.yandex_maps_key')) && filled(config('services.cdek.yandex_geocoder_key'));
    }

    /**
     * The goods total from which CDEK delivery is free; null when it is never free.
     */
    public function freeFrom(): ?float
    {
        $value = Setting::get('cdek.free_from');

        return $value === null ? null : (float) $value;
    }

    /**
     * The box size in centimetres.
     *
     * @return array{length: int, width: int, height: int}
     */
    public function box(): array
    {
        $box = (array) Setting::get('cdek.box', self::DEFAULT_BOX);

        return array_map(fn (string $side) => (int) ($box[$side] ?? self::DEFAULT_BOX[$side]), ['length' => 'length', 'width' => 'width', 'height' => 'height']);
    }

    /**
     * The whole cart as one parcel, in grams and centimetres.
     *
     * @return array{weight: int, length: int, width: int, height: int}
     */
    public function parcel(Cart $cart): array
    {
        $weight = $cart->lines()->sum(fn (CartLine $line) => ($line->variant->weight_g ?: self::DEFAULT_ITEM_WEIGHT) * $line->quantity);

        return ['weight' => (int) $weight + self::PACKAGING_WEIGHT, ...$this->box()];
    }

    /**
     * Look up a pickup point the customer picked on the map; null if CDEK does not know it.
     */
    public function pickupPoint(string $code): ?PickupPoint
    {
        return Cache::remember('cdek.point.'.md5($code), now()->addHours(6), fn () => $this->cdek->pickupPoint($code));
    }

    /**
     * What CDEK charges to take the cart to the point. Cached briefly, since
     * the summary is redrawn often while the customer fills in the form.
     */
    public function rate(PickupPoint $point, Cart $cart): CdekRate
    {
        $tariff = $point->isLocker ? self::TARIFF_LOCKER : self::TARIFF_PICKUP_POINT;
        $parcel = $this->parcel($cart);
        $from = (int) config('services.cdek.from_city_code');

        return Cache::remember(
            'cdek.rate.'.implode('.', [$tariff, $from, $point->cityCode, ...array_values($parcel)]),
            now()->addMinutes(30),
            fn () => $this->cdek->rate($tariff, $from, $point->cityCode, $parcel),
        );
    }

    /**
     * The delivery price for goods worth $goods (after any promo discount), in whole rubles.
     */
    public function cost(float $goods, CdekRate $rate): float
    {
        return $this->freeFrom() !== null && $goods >= $this->freeFrom() ? 0.0 : ceil($rate->price);
    }

    /**
     * How much more to add for free delivery; null when it is free already or never.
     */
    public function remainingForFree(float $goods): ?float
    {
        if ($this->freeFrom() === null || $goods >= $this->freeFrom()) {
            return null;
        }

        return $this->freeFrom() - $goods;
    }

    /**
     * The tariffs the map may offer, by the widget's delivery mode.
     *
     * @return array{office: list<int>, pickup: list<int>, door: list<int>}
     */
    public function widgetTariffs(): array
    {
        return ['office' => [self::TARIFF_PICKUP_POINT], 'pickup' => [self::TARIFF_LOCKER], 'door' => []];
    }
}
