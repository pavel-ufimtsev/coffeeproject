@php $sent = session('contact_sent'); @endphp
<x-layouts.shop :meta="[
    'title' => 'Кофе для кофеен, ресторанов и офисов оптом | '.config('shop.name'),
    'description' => 'Свежеобжаренный кофе оптом для кофеен, ресторанов, офисов и магазинов. Обжарка под заказ в Екатеринбурге, доставка по России, работа с юрлицами.',
    'canonical' => route('wholesale'),
]" class="site-main--flush">
    <section class="biz-hero">
        <div class="container biz-hero__inner">
            <p class="eyebrow">{{ __('For business') }}</p>
            <h1>Кофе для кофеен, ресторанов и&nbsp;офисов</h1>
            <p class="biz-hero__lead">Свежеобжаренный кофе по оптовым ценам с регулярной доставкой по всей России. Подберём сорта под ваше меню, оборудование и бюджет.</p>
            <p class="about-hero__actions">
                <a href="#request" class="button button--large">Оставить заявку</a>
                <a href="tel:{{ preg_replace('/[^\d+]/', '', config('shop.phone')) }}" class="button button--large button--secondary">{{ config('shop.phone') }}</a>
            </p>
        </div>
    </section>

    <section class="container about-section" aria-labelledby="for-whom-title">
        <header class="about-section__header">
            <p class="eyebrow">Кому подходит</p>
            <h2 id="for-whom-title">Работаем с теми, кому важен вкус в каждой чашке</h2>
        </header>
        <ul class="values">
            <li class="value">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10h13v4a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5Z"/><path d="M17 11.5h1.25a2.25 2.25 0 0 1 0 4.5H17M8.5 3.5c-.8 1 .8 2 0 3.5M12.5 3.5c-.8 1 .8 2 0 3.5"/></svg>
                <h3>Кофейни, рестораны, бары</h3>
                <p>Эспрессо-смеси с понятным вкусом для молочных напитков и моносорта для альтернативы. Поможем настроить помол и рецепт под вашу кофемашину.</p>
            </li>
            <li class="value">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="7" width="17" height="13" rx="2"/><path d="M8.5 7V5a1.5 1.5 0 0 1 1.5-1.5h4A1.5 1.5 0 0 1 15.5 5v2M3.5 12.5h17"/></svg>
                <h3>Офисы</h3>
                <p>Кофе для офисной кофемашины, капельной кофеварки или френч-пресса. Привозим по графику, чтобы кофе не заканчивался в понедельник утром.</p>
            </li>
            <li class="value">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9.5 5.5 4h13L20 9.5M4 9.5h16M4 9.5V20h16V9.5"/><path d="M9.5 20v-5h5v5"/></svg>
                <h3>Магазины и кулинарии</h3>
                <p>Кофе Coffee Project в пачках для продажи на полке: понятные описания вкуса и регулярное обновление ассортимента.</p>
            </li>
        </ul>
    </section>

    <section class="about-band" aria-labelledby="why-title">
        <div class="container about-split">
            <div class="about-split__text prose">
                <p class="eyebrow">Почему с нами удобно</p>
                <h2 id="why-title">Мы сами начинали с кофейни</h2>
                <p>Coffee Project открыл первую кофейню в Екатеринбурге в 2012 году, а потом годами поставлял кофе в кофейни и офисы по всей России. Мы знаем, что важно за стойкой: стабильный вкус, понятные сроки и кофе, который не заканчивается в самый неподходящий момент.</p>
                <ul class="checklist">
                    <li><strong>Обжарка под заказ.</strong> Кофе не лежит на складе, а приезжает к вам свежим.</li>
                    <li><strong>Один и тот же вкус.</strong> Следим, чтобы любимая смесь была одинаковой от поставки к поставке.</li>
                    <li><strong>Доставка по всей России.</strong> Курьер по Екатеринбургу, в другие города — СДЭК.</li>
                    <li><strong>Работаем с юрлицами и ИП.</strong> Счёт, договор и закрывающие документы.</li>
                </ul>
            </div>
            <figure class="about-split__photo">
                <img src="/images/about/cups.webp" width="1200" height="800" alt="Стаканы кофе Coffee Project на кофемашине" loading="lazy">
            </figure>
        </div>
    </section>

    <section class="container about-section" aria-labelledby="steps-title">
        <header class="about-section__header">
            <p class="eyebrow">Как начать</p>
            <h2 id="steps-title">Четыре шага до первой поставки</h2>
        </header>
        <ol class="biz-steps">
            <li><strong>Заявка</strong><span>Расскажите о заведении или офисе и примерном объёме.</span></li>
            <li><strong>Подбор</strong><span>Предложим сорта под ваше оборудование и пришлём оптовый прайс.</span></li>
            <li><strong>Пробная партия</strong><span>Попробуйте кофе в работе, прежде чем договариваться о регулярных поставках.</span></li>
            <li><strong>Поставки по графику</strong><span>Свежий кофе с нужной периодичностью, без лишних звонков.</span></li>
        </ol>
    </section>

    <section class="container about-section" id="request" aria-labelledby="request-title">
        <div class="biz-request">
            <div class="biz-request__intro">
                <p class="eyebrow">Заявка</p>
                <h2 id="request-title">Получить оптовый прайс</h2>
                <p class="about-section__lead">Оставьте контакты, и мы свяжемся с вами, чтобы подобрать кофе и рассказать об условиях.</p>
                <p class="muted">Удобнее позвонить? {{ config('shop.phone') }}<br>Или напишите на <a href="mailto:{{ config('shop.email') }}">{{ config('shop.email') }}</a></p>
            </div>

            <section class="contact-card" aria-label="{{ __('Request form') }}" data-contact>
                <div class="contact-thanks" role="status" tabindex="-1" data-contact-thanks @unless ($sent) hidden @endunless>
                    <svg class="contact-thanks__icon" viewBox="0 0 52 52" width="56" height="56" aria-hidden="true"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="2.5"/><path d="M15 27l7 7 15-16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <p class="contact-thanks__title">{{ __('Request sent') }}</p>
                    <p data-contact-thanks-text>{{ $sent }}</p>
                </div>

                <form method="POST" action="{{ route('wholesale.store') }}" class="contact-form" data-contact-form @if ($sent) hidden @endif
                    data-msg-network="{{ __('Could not send the message. Check your connection and try again, or write to :email.', ['email' => config('shop.email')]) }}"
                    data-msg-throttle="{{ __('Too many messages in a row. Please wait a minute and try again.') }}">
                    @csrf
                    <p class="contact-form__error" role="alert" data-contact-error hidden></p>

                    <div class="contact-form__row">
                        <x-checkout.field name="name" id="biz-name" :label="__('Your name')" required maxlength="100" autocomplete="name" :data-msg-required="__('Tell us your name.')" />
                        <x-checkout.field name="company" id="biz-company" :label="__('Company or venue')" maxlength="150" autocomplete="organization" />
                    </div>
                    <div class="contact-form__row">
                        <x-checkout.field name="phone" id="biz-phone" type="tel" :label="__('Phone')" required maxlength="30" autocomplete="tel" inputmode="tel" data-phone
                            :data-msg-required="__('Enter your phone number.')" :data-msg-invalid="__('Enter a Russian phone number, for example +7 909 701-90-07.')" />
                        <x-checkout.field name="email" id="biz-email" type="email" :label="__('Email')" required maxlength="255" autocomplete="email" inputmode="email"
                            :data-msg-required="__('Enter your email so we can reply.')" :data-msg-invalid="__('Enter an email like name@example.ru.')" />
                    </div>
                    <x-checkout.field name="city" id="biz-city" :label="__('City')" maxlength="100" autocomplete="address-level2" />

                    <fieldset class="topics" data-field>
                        <legend>{{ __('Who is the coffee for?') }}</legend>
                        <div class="topics__list">
                            @foreach ($businesses as $value => $label)
                                <label class="topic">
                                    <input type="radio" name="business" value="{{ $value }}" required @checked(old('business', 'cafe') === $value) data-msg-required="{{ __('Choose who the coffee is for.') }}">
                                    <span>{{ __($label) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="float-field__error" id="biz-business-error" hidden></p>
                    </fieldset>

                    <fieldset class="topics" data-field>
                        <legend>{{ __('About how much coffee?') }}</legend>
                        <div class="topics__list">
                            @foreach ($volumes as $value => $label)
                                <label class="topic">
                                    <input type="radio" name="volume" value="{{ $value }}" required @checked(old('volume', 'unknown') === $value) data-msg-required="{{ __('Choose a rough volume.') }}">
                                    <span>{{ __($label) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="float-field__error" id="biz-volume-error" hidden></p>
                    </fieldset>

                    <div class="contact-form__message">
                        <x-checkout.field name="message" id="biz-message" type="textarea" :label="__('Equipment, current coffee, wishes')" maxlength="5000" />
                    </div>

                    <div class="contact-form__trap" aria-hidden="true">
                        <label for="biz-website">Website</label>
                        <input type="text" id="biz-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <label class="checkout-consent" data-field>
                        <input type="checkbox" name="consent" value="1" required data-msg-required="{{ __('Please agree to the processing of your personal data.') }}">
                        <span>{!! __('I agree to the processing of my personal data under the <a href=":privacy" target="_blank">privacy policy</a>.', ['privacy' => route('legal.privacy')]) !!}</span>
                        <span class="float-field__error" id="biz-consent-error" hidden></span>
                    </label>

                    <button type="submit" class="button button--large contact-form__submit" data-contact-submit>
                        <span data-contact-submit-label data-busy-label="{{ __('Sending…') }}">{{ __('Get prices') }}</span>
                    </button>
                </form>
            </section>
        </div>
    </section>
</x-layouts.shop>
