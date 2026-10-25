@php
    use App\Enums\PaymentMethod;
    use App\Models\Address;
    use App\Support\Money;
    $address = $selectedAddress;
    $addressValue = fn (string $field) => $address?->{$field} ?? ($field === 'city' ? config('shop.courier_city') : null);
    $paymentMethods = $checkout->paymentMethods();
@endphp
<x-layouts.checkout :title="__('Checkout')">
    <div class="checkout" data-checkout data-summary-url="{{ route('checkout.summary') }}">
        <div class="checkout__main">
            <nav class="checkout-steps" aria-label="{{ __('Checkout steps') }}">
                <a href="{{ route('cart.show') }}">{{ __('Cart') }}</a>
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                <span aria-current="step">{{ __('Checkout') }}</span>
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                <span>{{ __('Done') }}</span>
            </nav>

            <form method="POST" action="{{ route('checkout.store') }}" class="checkout-form" data-checkout-form>
                @csrf
                <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
                <input type="hidden" name="expected_total" value="{{ $quote->total() }}" data-expected-total>

                @if ($errors->hasAny(['total', 'promo']))
                    <div class="notice notice--warning" role="alert">
                        <p>{{ $errors->first('total') ?: $errors->first('promo') }}</p>
                    </div>
                @elseif ($errors->any())
                    <div class="notice notice--warning" role="alert">
                        <p>{{ __('Please check the highlighted fields.') }}</p>
                    </div>
                @endif

                <section class="checkout-section" aria-labelledby="checkout-contacts">
                    <div class="checkout-section__head">
                        <h2 id="checkout-contacts">{{ __('Contacts') }}</h2>
                        @guest
                            <p class="checkout-section__aside">{{ __('Have an account?') }} <a href="{{ route('checkout.login') }}">{{ __('Log in') }}</a></p>
                        @endguest
                    </div>
                    <div class="checkout-grid">
                        <x-checkout.field name="phone" type="tel" :label="__('Phone')" :value="$contact['phone']" span="half" required autocomplete="tel" inputmode="tel" maxlength="30" data-phone
                            :data-msg-required="__('Enter your phone number.')" :data-msg-invalid="__('Enter a Russian phone number, for example +7 909 701-90-07.')" />
                        <x-checkout.field name="email" type="email" :label="__('Email')" :value="$contact['email']" span="half" required autocomplete="email" maxlength="255" spellcheck="false"
                            :data-msg-required="__('Enter your email so we can send the order confirmation.')" :data-msg-invalid="__('Enter an email like name@example.ru.')" />
                        <x-checkout.field name="first_name" :label="__('First name')" :value="$contact['first_name']" span="half" required autocomplete="given-name" maxlength="100"
                            :data-msg-required="__('Enter your first name.')" />
                        <x-checkout.field name="last_name" :label="__('Last name')" :value="$contact['last_name']" span="half" required autocomplete="family-name" maxlength="100"
                            :data-msg-required="__('Enter your last name.')" />
                    </div>
                    <p class="checkout-hint">{{ __('We will call to confirm the order and send the confirmation by email.') }}</p>
                </section>

                <section class="checkout-section" aria-labelledby="checkout-delivery">
                    <div class="checkout-section__head">
                        <h2 id="checkout-delivery">{{ __('Delivery') }}</h2>
                    </div>

                    <div class="checkout-delivery" data-delivery>
                        @include('shop.checkout.delivery-options')
                    </div>

                    <fieldset class="checkout-address">
                        <legend class="checkout-address__legend">{{ __('Delivery address') }}</legend>

                        @auth
                            @if ($addresses->isNotEmpty())
                                <input type="hidden" name="address_id" value="{{ $isNewAddress ? 'new' : $address->id }}" data-address-id>
                                <div class="address-picker" data-address-picker data-default-city="{{ config('shop.courier_city') }}">
                                    @foreach ($addresses as $saved)
                                        <a href="{{ route('checkout.show', ['address' => $saved->id]) }}" class="address-chip"
                                            data-address-choice="{{ $saved->id }}" data-label="{{ $saved->label }}" data-fields="{{ json_encode($saved->addressAttributes(), JSON_UNESCAPED_UNICODE) }}"
                                            @if (! $isNewAddress && $address->is($saved)) aria-current="true" @endif>
                                            {{ $saved->label }}
                                        </a>
                                    @endforeach
                                    @if ($addresses->count() < Address::MAX_PER_USER)
                                        <a href="{{ route('checkout.show', ['address' => 'new']) }}" class="address-chip address-chip--new" data-address-choice="new" @if ($isNewAddress) aria-current="true" @endif>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                            {{ __('New address') }}
                                        </a>
                                    @endif
                                </div>
                                <p class="checkout-hint" data-address-note data-template="{{ __('Changes will be saved to the address “:label”.') }}" @if ($isNewAddress) hidden @endif>
                                    {{ $isNewAddress ? '' : __('Changes will be saved to the address “:label”.', ['label' => $address->label]) }}
                                </p>
                            @else
                                <input type="hidden" name="address_id" value="new">
                            @endif
                        @endauth

                        <div class="checkout-grid">
                            @auth
                                <div class="float-field-wrap float-field--full" data-address-label @unless ($isNewAddress) hidden @endunless>
                                    <x-checkout.field name="address_label" :label="__('Address name, e.g. Home or Work')" :value="$addresses->isEmpty() ? __('Home address') : ''" maxlength="50"
                                        :required="$isNewAddress" :data-msg-required="__('Name the address so you can pick it next time.')" />
                                    <p class="checkout-hint">{{ __('We will save the address to your account under this name.') }}</p>
                                </div>
                            @endauth
                            <x-checkout.field name="city" :label="__('City')" :value="$addressValue('city')" span="third" required autocomplete="address-level2" maxlength="100" data-address-field
                                :data-msg-required="__('Enter the city.')" />
                            <x-checkout.field name="street" :label="__('Street')" :value="$addressValue('street')" span="two-thirds" required autocomplete="address-line1" maxlength="255" data-address-field
                                :data-msg-required="__('Enter the street.')" />
                            <x-checkout.field name="house" :label="__('House')" :value="$addressValue('house')" span="third" required maxlength="30" data-address-field
                                :data-msg-required="__('Enter the house number.')" />
                            <x-checkout.field name="apartment" :label="__('Apt. / office')" :value="$addressValue('apartment')" span="third" autocomplete="address-line2" maxlength="30" data-address-field />
                            <x-checkout.field name="entrance" :label="__('Entrance')" :value="$addressValue('entrance')" span="third" maxlength="10" data-address-field />
                            <x-checkout.field name="floor" :label="__('Floor')" :value="$addressValue('floor')" span="half" maxlength="10" data-address-field />
                            <x-checkout.field name="intercom" :label="__('Intercom')" :value="$addressValue('intercom')" span="half" maxlength="30" data-address-field />
                            <x-checkout.field name="courier_note" :label="__('Note for the courier (optional)')" :value="$addressValue('courier_note')" span="full" maxlength="500" data-address-field />
                        </div>
                    </fieldset>
                </section>

                <section class="checkout-section" aria-labelledby="checkout-payment">
                    <div class="checkout-section__head">
                        <h2 id="checkout-payment">{{ __('Payment') }}</h2>
                    </div>
                    @if ($paymentMethods === [])
                        <p class="notice notice--warning">{{ __('Online ordering is paused. Please call us at :phone to order.', ['phone' => config('shop.phone')]) }}</p>
                    @else
                        <div class="choice-list" role="radiogroup" aria-label="{{ __('Payment method') }}">
                            @foreach ($paymentMethods as $method)
                                <label class="choice">
                                    <input type="radio" name="payment_method" value="{{ $method->value }}" @checked(old('payment_method', $paymentMethods[0]->value) === $method->value)
                                        data-payment-method data-online="{{ $method->isOnline() ? '1' : '0' }}">
                                    <span class="choice__body">
                                        <span class="choice__title">{{ $method->label() }}</span>
                                        <span class="choice__note">
                                            {{ $method->isOnline() ? __('Card, SBP or T-Pay on the secure T-Bank page.') : __('Pay the courier when you receive the order.') }}
                                        </span>
                                    </span>
                                    @if ($method->isOnline())
                                        <svg class="choice__icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/></svg>
                                    @else
                                        <svg class="choice__icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v5M18 9.5v5"/></svg>
                                    @endif
                                </label>
                            @endforeach
                            @unless (in_array(PaymentMethod::TBank, $paymentMethods, true))
                                <div class="choice choice--disabled" aria-disabled="true">
                                    <span class="choice__radio" aria-hidden="true"></span>
                                    <span class="choice__body">
                                        <span class="choice__title">{{ __('Card online') }}</span>
                                        <span class="choice__note">{{ __('T-Bank, SBP') }}</span>
                                    </span>
                                    <span class="choice__badge">{{ __('Soon') }}</span>
                                </div>
                            @endunless
                        </div>
                    @endif
                </section>

                <section class="checkout-section checkout-section--compact">
                    <details class="checkout-comment" @if (old('comment') || $errors->has('comment')) open @endif>
                        <summary>{{ __('Add a comment to the order') }}</summary>
                        <x-checkout.field name="comment" type="textarea" :label="__('Order comment')" maxlength="1000" />
                    </details>
                </section>

                <div class="checkout-submit">
                    <label class="checkout-consent" data-field>
                        <input type="checkbox" name="consent" value="1" required @checked(old('consent')) data-msg-required="{{ __('Please agree to the processing of your personal data.') }}">
                        <span>{!! __('I agree to the processing of my personal data under the <a href=":privacy" target="_blank">privacy policy</a>.', ['privacy' => route('legal.privacy')]) !!}</span>
                        <span class="float-field__error" id="checkout-consent-error" @unless ($errors->has('consent')) hidden @endunless>{{ $errors->first('consent') }}</span>
                    </label>

                    <button type="submit" class="button button--block button--large checkout-submit__button" data-checkout-submit
                        data-busy-label="{{ __('Placing your order…') }}" @disabled(! $checkout->isOpen())>
                        <span data-submit-label data-label-offline="{{ __('Place order') }}" data-label-online="{{ __('Go to payment') }}"><span data-submit-text>{{ __('Place order') }}</span> · <span data-checkout-total>{{ Money::rub($quote->total()) }}</span></span>
                    </button>

                    <p class="checkout-hint checkout-hint--center" data-online-hint hidden>{{ __('Next you go to the secure T-Bank page; the order is confirmed once it is paid.') }}</p>

                    <p class="checkout-legal">{!! __('By placing the order you accept the <a href=":terms" target="_blank">terms of use</a>.', ['terms' => route('legal.terms')]) !!}</p>

                    <a href="{{ route('cart.show') }}" class="checkout-back">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
                        {{ __('Back to cart') }}
                    </a>
                </div>
            </form>

            <footer class="checkout-footer">
                <a href="{{ route('legal.terms') }}">{{ __('Terms of use') }}</a>
                <a href="{{ route('legal.privacy') }}">{{ __('Privacy policy') }}</a>
                <a href="tel:{{ preg_replace('/[^\d+]/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a>
            </footer>
        </div>

        <aside class="checkout__aside" aria-label="{{ __('Order summary') }}" data-checkout-aside>
            <button type="button" class="summary-toggle" aria-expanded="false" aria-controls="checkout-summary" data-summary-toggle>
                <span class="summary-toggle__label">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.2 11.2a1.5 1.5 0 0 0 1.5 1.3h8.6a1.5 1.5 0 0 0 1.5-1.2L20.5 8H6.2"/><circle cx="9.5" cy="20" r="1.2"/><circle cx="17" cy="20" r="1.2"/></svg>
                    <span data-toggle-text data-show="{{ __('Show order') }}" data-hide="{{ __('Hide order') }}">{{ __('Show order') }}</span>
                    <svg class="summary-toggle__chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </span>
                <strong data-checkout-total>{{ Money::rub($quote->total()) }}</strong>
            </button>
            <div class="checkout-summary" id="checkout-summary" data-summary>
                @include('shop.checkout.summary')
            </div>
        </aside>
    </div>
</x-layouts.checkout>
