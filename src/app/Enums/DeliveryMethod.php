<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    /** Our courier, Ekaterinburg only. Price and free threshold are set in the admin. */
    case Courier = 'courier';

    /** CDEK to a pickup point or parcel locker across Russia; paid by card online. */
    case Cdek = 'cdek';

    /**
     * Get the human-readable name of the method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Courier => __('Courier'),
            self::Cdek => __('CDEK'),
        };
    }

    /**
     * Get the delivery type code in retailCRM.
     */
    public function retailCrmCode(): string
    {
        return match ($this) {
            self::Courier => 'courier',
            self::Cdek => 'sdek-v-2-01f0f8b2889c8e17ac733e519dc2b44f',
        };
    }
}
