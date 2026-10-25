<?php

namespace App\Models\Concerns;

/**
 * The address columns shared by saved addresses and orders.
 */
trait HasDeliveryAddress
{
    /**
     * The address columns, in the order they appear on forms.
     */
    public const ADDRESS_FIELDS = ['city', 'street', 'house', 'apartment', 'entrance', 'floor', 'intercom', 'courier_note'];

    /**
     * The address on one line: "Екатеринбург, ул. Вайнера, д. 36, кв. 28".
     */
    public function addressLine(): string
    {
        return collect([
            $this->city,
            $this->street,
            filled($this->house) ? __('bld. :house', ['house' => $this->house]) : null,
            // "28" becomes "кв. 28"; "офис 305" stays as typed.
            match (true) {
                blank($this->apartment) => null,
                ctype_digit(mb_substr($this->apartment, 0, 1)) => __('apt. :apartment', ['apartment' => $this->apartment]),
                default => $this->apartment,
            },
        ])->filter()->implode(', ');
    }

    /**
     * Entrance, floor and intercom, for the courier: "подъезд 2, этаж 5, домофон 28К".
     */
    public function accessLine(): string
    {
        return collect([
            filled($this->entrance) ? __('entrance :value', ['value' => $this->entrance]) : null,
            filled($this->floor) ? __('floor :value', ['value' => $this->floor]) : null,
            filled($this->intercom) ? __('intercom :value', ['value' => $this->intercom]) : null,
        ])->filter()->implode(', ');
    }

    /**
     * @return array<string, string|null>
     */
    public function addressAttributes(): array
    {
        return $this->only(self::ADDRESS_FIELDS);
    }
}
