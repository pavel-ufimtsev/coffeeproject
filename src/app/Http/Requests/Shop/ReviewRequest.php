<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'brew_method' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Tell us your name.'),
            'email.required' => __('Enter your email. We will not show it on the site.'),
            'email.email' => __('Enter an email like name@example.ru.'),
            'rating.required' => __('Rate the coffee from one to five stars.'),
            'body.required' => __('Write a few words about the coffee.'),
            'body.min' => __('Tell us a little more, at least :min characters.'),
            'consent.accepted' => __('Please agree to the processing of your personal data.'),
            'website.prohibited' => __('Your message looks like spam.'),
        ];
    }
}
