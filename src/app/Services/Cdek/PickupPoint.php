<?php

namespace App\Services\Cdek;

/**
 * A CDEK pickup point or parcel locker, as the API describes it.
 */
class PickupPoint
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly bool $isLocker,
        public readonly int $cityCode,
        public readonly string $city,
        public readonly string $address,
        public readonly ?string $workTime,
    ) {}

    /**
     * @param  array<string, mixed>  $point  One item of GET /deliverypoints
     */
    public static function fromApi(array $point): self
    {
        return new self(
            code: (string) $point['code'],
            name: (string) ($point['name'] ?? $point['code']),
            isLocker: ($point['type'] ?? null) === 'POSTAMAT',
            cityCode: (int) data_get($point, 'location.city_code'),
            city: (string) data_get($point, 'location.city', ''),
            address: (string) data_get($point, 'location.address', ''),
            workTime: $point['work_time'] ?? null,
        );
    }

    /**
     * "Пункт выдачи СДЭК" or "Постамат СДЭК".
     */
    public function kind(): string
    {
        return $this->isLocker ? __('CDEK parcel locker') : __('CDEK pickup point');
    }
}
