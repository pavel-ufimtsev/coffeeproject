<?php

namespace App\Enums;

enum OrderStatus: string
{
    /** Just placed; nobody has looked at it yet. */
    case New = 'new';

    /** Checked and confirmed with the customer. */
    case Confirmed = 'confirmed';

    /** Handed to the courier. */
    case Shipped = 'shipped';

    /** Delivered and paid. */
    case Completed = 'completed';

    case Cancelled = 'cancelled';

    /**
     * Get the human-readable name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Confirmed => __('Confirmed'),
            self::Shipped => __('Shipped'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * Get the statuses as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
