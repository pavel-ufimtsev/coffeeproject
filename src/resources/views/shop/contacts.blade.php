@php
    use App\Support\OrganizationSchema;

    $phoneHref = 'tel:'.preg_replace('/[^\d+]/', '', config('shop.phone'));
    $sent = session('contact_sent');
@endphp
<x-layouts.shop :meta="[
    'title' => __('Contacts').' | '.config('shop.name'),
    'description' => 'Контакты Coffee Project: телефон, почта и форма обратной связи. Поможем выбрать кофе, ответим на вопросы о заказе, доставке и оптовых поставках.',
    'canonical' => route('contacts'),
]" :json-ld="[OrganizationSchema::page('ContactPage', __('Contacts'), route('contacts'))]">
    <div class="container contact-page">
        <header class="contact-page__header">
            <p class="eyebrow">{{ __('Contacts') }}</p>
            <h1>{{ __('Contact us') }}</h1>
            <p class="contact-page__lead">{{ __('Questions about an order, help choosing coffee, wholesale or press: write to us and a real person will reply.') }}</p>
        </header>

        <div class="contact-layout">
            <section class="contact-card" aria-labelledby="contact-form-title" data-contact>
                <h2 id="contact-form-title" class="contact-card__title">{{ __('Write to us') }}</h2>

                <div class="contact-thanks" role="status" tabindex="-1" data-contact-thanks @unless ($sent) hidden @endunless>
                    <svg class="contact-thanks__icon" viewBox="0 0 52 52" width="56" height="56" aria-hidden="true"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="2.5"/><path d="M15 27l7 7 15-16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <p class="contact-thanks__title">{{ __('Message sent') }}</p>
                    <p data-contact-thanks-text>{{ $sent }}</p>
                    <button type="button" class="link-button" data-contact-again>{{ __('Send another message') }}</button>
                </div>

                <form method="POST" action="{{ route('contacts.store') }}" class="contact-form" data-contact-form @if ($sent) hidden @endif
                    data-msg-network="{{ __('Could not send the message. Check your connection and try again, or write to :email.', ['email' => config('shop.email')]) }}"
                    data-msg-throttle="{{ __('Too many messages in a row. Please wait a minute and try again.') }}">
                    @csrf

                    <p class="contact-form__error" role="alert" data-contact-error hidden></p>

                    <div class="contact-form__row">
                        <x-checkout.field name="name" id="contact-name" :label="__('Your name')" required maxlength="100" autocomplete="name"
                            :data-msg-required="__('Tell us your name.')" />
                        <x-checkout.field name="email" id="contact-email" type="email" :label="__('Email')" required maxlength="255" autocomplete="email" inputmode="email"
                            :data-msg-required="__('Enter your email so we can reply.')" :data-msg-invalid="__('Enter an email like name@example.ru.')" />
                    </div>

                    <x-checkout.field name="phone" id="contact-phone" type="tel" :label="__('Phone, if you prefer a call')" maxlength="30" autocomplete="tel" inputmode="tel" data-phone
                        :data-msg-invalid="__('Enter a Russian phone number, for example +7 909 701-90-07.')" />

                    <fieldset class="topics" data-field>
                        <legend>{{ __('What is it about?') }}</legend>
                        <div class="topics__list">
                            @foreach ($topics as $value => $label)
                                <label class="topic">
                                    <input type="radio" name="topic" value="{{ $value }}" required @checked(old('topic', 'order') === $value)
                                        data-msg-required="{{ __('Choose what your message is about.') }}">
                                    <span>{{ __($label) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="float-field__error" id="contact-topic-error" @unless ($errors->has('topic')) hidden @endunless>{{ $errors->first('topic') }}</p>
                    </fieldset>

                    <div class="contact-form__message">
                        <x-checkout.field name="message" id="contact-message" type="textarea" :label="__('Message')" required minlength="10" maxlength="5000"
                            :data-msg-required="__('Write your message.')" :data-msg-short="__('Tell us a little more, at least :min characters.', ['min' => 10])" />
                        <p class="contact-form__counter" aria-live="polite" data-contact-counter hidden></p>
                    </div>

                    {{-- Honeypot: people never see it, bots fill it in. --}}
                    <div class="contact-form__trap" aria-hidden="true">
                        <label for="contact-website">Website</label>
                        <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <label class="checkout-consent" data-field>
                        <input type="checkbox" name="consent" value="1" required @checked(old('consent')) data-msg-required="{{ __('Please agree to the processing of your personal data.') }}">
                        <span>{!! __('I agree to the processing of my personal data under the <a href=":privacy" target="_blank">privacy policy</a>.', ['privacy' => route('legal.privacy')]) !!}</span>
                        <span class="float-field__error" id="contact-consent-error" @unless ($errors->has('consent')) hidden @endunless>{{ $errors->first('consent') }}</span>
                    </label>

                    <button type="submit" class="button button--large contact-form__submit" data-contact-submit>
                        <span data-contact-submit-label data-busy-label="{{ __('Sending…') }}">{{ __('Send message') }}</span>
                    </button>
                </form>
            </section>

            <aside class="contact-aside" aria-label="{{ __('Other ways to reach us') }}">
                <ul class="contact-ways">
                    <li>
                        <a class="contact-way" href="{{ $phoneHref }}">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>
                            <span>
                                <span class="contact-way__label">{{ __('Phone') }}</span>
                                <span class="contact-way__value">{{ config('shop.phone') }}</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="contact-way" href="mailto:{{ config('shop.email') }}">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            <span>
                                <span class="contact-way__label">{{ __('Email') }}</span>
                                <span class="contact-way__value">{{ config('shop.email') }}</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="contact-way" href="{{ config('shop.vk') }}" rel="noopener" target="_blank">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>
                            <span>
                                <span class="contact-way__label">{{ __('VK community') }}</span>
                                <span class="contact-way__value">vk.com/coffee_project</span>
                            </span>
                        </a>
                    </li>
                </ul>

                <div class="contact-info">
                    <h2 class="contact-info__title">{{ __('Office') }}</h2>
                    <p>{{ config('shop.legal_name') }}<br>{{ config('shop.legal_address') }}</p>
                    <p class="muted">{{ __('We roast and ship from Ekaterinburg. Visits to the office by appointment only.') }}</p>
                </div>

                <div class="contact-info">
                    <h2 class="contact-info__title">{{ __('Quick answers') }}</h2>
                    <ul class="contact-links">
                        @auth
                            <li><a href="{{ route('account.index') }}">{{ __('Your orders and their status') }}</a></li>
                        @endauth
                        <li><a href="{{ route('legal.terms') }}#delivery">{{ __('Delivery') }}</a></li>
                        <li><a href="{{ route('legal.terms') }}#returns">{{ __('Returns and exchanges') }}</a></li>
                        <li><a href="{{ route('about') }}">{{ __('About Coffee Project') }}</a></li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.shop>
