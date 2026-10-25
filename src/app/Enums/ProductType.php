<?php

namespace App\Enums;

enum ProductType: string
{
    case Coffee = 'coffee';
    case Drip = 'drip';
    case Tea = 'tea';
    case Accessory = 'accessory';
    case Certificate = 'certificate';
    case Guide = 'guide';

    /**
     * Get the human-readable name of the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Coffee => __('Coffee beans'),
            self::Drip => __('Drip bags'),
            self::Tea => __('Tea'),
            self::Accessory => __('Accessory'),
            self::Certificate => __('Gift certificate'),
            self::Guide => __('Guide'),
        };
    }

    /**
     * Get the spec fields products of this type can have, keyed by spec name,
     * in the order they are shown.
     *
     * @return array<string, string>
     */
    public function specFields(): array
    {
        return match ($this) {
            self::Coffee, self::Drip => [
                'country' => __('Country'),
                'region' => __('Region'),
                'farm' => __('Farm'),
                'altitude' => __('Altitude'),
                'harvest' => __('Harvest'),
                'process' => __('Process'),
                'varieties' => __('Varieties'),
                'roast' => __('Roast'),
                'sca_score' => __('SCA score'),
                'tasting_notes' => __('Tasting notes'),
            ],
            default => [],
        };
    }

    /**
     * Determine whether shoppers choose a grind for this type.
     */
    public function hasGrind(): bool
    {
        return $this === self::Coffee;
    }

    /**
     * Get the grind choices offered at purchase, keyed by the value sent with the order.
     *
     * @return array<string, string>
     */
    public static function grinds(): array
    {
        return [
            'whole' => __('Whole beans'),
            'cezve' => __('For cezve'),
            'espresso' => __('For espresso machine'),
            'aeropress' => __('For AeroPress'),
            'pourover' => __('For pour-over, V60, Chemex'),
            'drip' => __('For drip coffee maker'),
            'moka' => __('For moka pot'),
            'french' => __('For French press'),
        ];
    }
}
