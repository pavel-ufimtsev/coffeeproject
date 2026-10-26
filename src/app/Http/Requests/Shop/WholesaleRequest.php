<?php

namespace App\Http\Requests\Shop;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WholesaleRequest extends FormRequest
{
    /** Who the coffee is for, as English keys for __(). */
    public const BUSINESSES = [
        'cafe' => 'Coffee shop, restaurant or bar',
        'office' => 'Office',
        'retail' => 'Shop or resale',
        'other' => 'Something else',
    ];

    /** Rough monthly volume, as English keys for __(). */
    public const VOLUMES = [
        'small' => 'Up to 5 kg a month',
        'medium' => '5 to 20 kg a month',
        'large' => '20 to 50 kg a month',
        'xl' => 'More than 50 kg a month',
        'unknown' => 'Not sure yet',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'business' => ['required', Rule::in(array_keys(self::BUSINESSES))],
            'volume' => ['required', Rule::in(array_keys(self::VOLUMES))],
            'message' => ['nullable', 'string', 'max:5000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'prohibited'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('phone') && ! $validator->errors()->has('phone') && Phone::normalize($this->input('phone')) === null) {
                    $validator->errors()->add('phone', __('Enter a Russian phone number, for example +7 909 701-90-07.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Tell us your name.'),
            'email.required' => __('Enter your email so we can reply.'),
            'email.email' => __('Enter an email like name@example.ru.'),
            'phone.required' => __('Enter your phone number.'),
            'business.required' => __('Choose who the coffee is for.'),
            'volume.required' => __('Choose a rough volume.'),
            'consent.accepted' => __('Please agree to the processing of your personal data.'),
            'website.prohibited' => __('Your message looks like spam.'),
        ];
    }
}
