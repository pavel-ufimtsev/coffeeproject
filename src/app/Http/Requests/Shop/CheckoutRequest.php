<?php

namespace App\Http\Requests\Shop;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Services\Checkout\Checkout;
use App\Support\AddressRules;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $checkout = app(Checkout::class);
        $user = $this->user();
        // A CDEK order goes to a pickup point: no street address to fill in or save.
        $address = $this->isPickup() ? array_map(fn () => ['exclude'], AddressRules::rules()) : AddressRules::rules();

        return [
            'checkout_token' => ['required', 'string', 'max:40'],
            'expected_total' => ['nullable', 'numeric'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'delivery_method' => ['required', Rule::in(array_map(fn (DeliveryMethod $m) => $m->value, $checkout->deliveryMethods()))],
            'address_id' => match (true) {
                $this->isPickup() => ['exclude'],
                $user !== null => ['nullable', Rule::in(['new', ...$user->addresses()->pluck('id')->map(fn ($id) => (string) $id)])],
                default => ['prohibited'],
            },
            'address_label' => $user && ! $this->isPickup() && $this->wantsNewAddress()
                ? ['required', 'string', 'max:50', Rule::unique('addresses', 'label')->where('user_id', $user->id)]
                : ['exclude'],
            ...$address,
            'pickup_point' => $this->isPickup() ? ['required', 'string', 'max:30'] : ['exclude'],
            'payment_method' => ['required', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, $checkout->paymentMethods(DeliveryMethod::tryFrom((string) $this->input('delivery_method')))))],
            'comment' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->has('phone') && Phone::normalize($this->input('phone')) === null) {
                    $validator->errors()->add('phone', __('Enter a Russian phone number, for example +7 909 701-90-07.'));
                }

                if (! $validator->errors()->has('city') && $this->input('delivery_method') === DeliveryMethod::Courier->value && ! AddressRules::isCourierCity($this->input('city'))) {
                    $validator->errors()->add('city', in_array(DeliveryMethod::Cdek, app(Checkout::class)->deliveryMethods(), true)
                        ? __('Our courier delivers in :city only. For other cities choose CDEK.', ['city' => config('shop.courier_city')])
                        : __('Our courier delivers in :city only.', ['city' => config('shop.courier_city')]));
                }

                if ($this->user() && ! $this->isPickup() && $this->wantsNewAddress() && $this->user()->addresses()->count() >= Address::MAX_PER_USER) {
                    $validator->errors()->add('address_label', __('You can save up to :count addresses. Remove one in your account first.', ['count' => Address::MAX_PER_USER]));
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
            ...AddressRules::attributes(),
            'phone' => __('Phone'),
            'email' => __('Email'),
            'first_name' => __('First name'),
            'last_name' => __('Last name'),
            'address_label' => __('Address name'),
            'delivery_method' => __('Delivery method'),
            'payment_method' => __('Payment method'),
            'pickup_point' => __('Pickup point'),
            'comment' => __('Order comment'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => __('Please agree to the processing of your personal data.'),
            'address_label.unique' => __('You already have an address with this name.'),
            'pickup_point.required' => __('Choose a pickup point on the map.'),
        ];
    }

    /**
     * Whether an account holder is adding an address rather than editing a saved one.
     */
    public function wantsNewAddress(): bool
    {
        return $this->input('address_id', 'new') === 'new' || blank($this->input('address_id'));
    }

    /**
     * Whether the order goes to a CDEK pickup point rather than by courier.
     */
    public function isPickup(): bool
    {
        return $this->input('delivery_method') === DeliveryMethod::Cdek->value;
    }

    public function phone(): string
    {
        return Phone::normalize($this->input('phone'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['email', 'first_name', 'last_name', 'address_label', ...array_keys(AddressRules::rules())]))
            ->map(fn ($value) => is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value)) : $value)
            ->all());

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }
}
