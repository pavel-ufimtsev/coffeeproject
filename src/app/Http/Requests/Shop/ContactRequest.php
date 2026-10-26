<?php

namespace App\Http\Requests\Shop;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContactRequest extends FormRequest
{
    /**
     * What the message is about, as English keys for __(). The key is what
     * the form posts.
     */
    public const TOPICS = [
        'order' => 'An order',
        'coffee' => 'Choosing coffee',
        'wholesale' => 'Wholesale and offices',
        'press' => 'Press and partnership',
        'other' => 'Something else',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'topic' => ['required', Rule::in(array_keys(self::TOPICS))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
            // Honeypot: hidden from people, filled in by bots.
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
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'topic' => __('Topic'),
            'message' => __('Message'),
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
            'topic.required' => __('Choose what your message is about.'),
            'message.required' => __('Write your message.'),
            'message.min' => __('Tell us a little more, at least :min characters.'),
            'consent.accepted' => __('Please agree to the processing of your personal data.'),
            'website.prohibited' => __('Your message looks like spam.'),
        ];
    }
}
