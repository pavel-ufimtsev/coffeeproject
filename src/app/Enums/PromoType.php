<?php

namespace App\Enums;

enum PromoType: string
{
    /** A percentage off the goods. */
    case Percent = 'percent';

    /** A fixed amount in rubles off the goods. */
    case Fixed = 'fixed';

    /**
     * Get the human-readable name of the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Percent => __('Percent off'),
            self::Fixed => __('Amount off'),
        };
    }

    /**
     * Get the types as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
