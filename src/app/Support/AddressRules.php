<?php

namespace App\Support;

/**
 * Validation for the address fields, shared by checkout and the address book.
 */
class AddressRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'city' => ['required', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:255'],
            'house' => ['required', 'string', 'max:30'],
            'apartment' => ['nullable', 'string', 'max:30'],
            'entrance' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'intercom' => ['nullable', 'string', 'max:30'],
            'courier_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'label' => __('Address name'),
            'city' => __('City'),
            'street' => __('Street'),
            'house' => __('House'),
            'apartment' => __('Apartment or office'),
            'entrance' => __('Entrance'),
            'floor' => __('Floor'),
            'intercom' => __('Intercom'),
            'courier_note' => __('Note for the courier'),
        ];
    }

    /**
     * Whether a city is the one the courier serves: "г. Екатеринбург" and
     * "екатеринбург" count, "Березовский" does not.
     */
    public static function isCourierCity(?string $city): bool
    {
        $normalize = fn (?string $value) => str_replace('ё', 'е', trim(preg_replace('/^(г\.|город)\s*/u', '', mb_strtolower(trim((string) $value)))));

        return $normalize($city) === $normalize(config('shop.courier_city'));
    }
}
