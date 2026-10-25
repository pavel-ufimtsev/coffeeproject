@props(['title' => null, 'meta' => [], 'jsonLd' => []])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php($pageTitle = $meta['title'] ?? (($title ? $title.' | ' : '').config('app.name')))
        <title>{{ $pageTitle }}</title>
        @isset($meta['description'])
            <meta name="description" content="{{ $meta['description'] }}">
        @endisset
        @isset($meta['robots'])
            <meta name="robots" content="{{ $meta['robots'] }}">
        @endisset
        @isset($meta['canonical'])
            <link rel="canonical" href="{{ $meta['canonical'] }}">
        @endisset

        {{-- Link previews in messengers and social networks; pages without a photo get the brand card. --}}
        <meta property="og:type" content="{{ $meta['og_type'] ?? 'website' }}">
        <meta property="og:site_name" content="{{ config('shop.name') }}">
        <meta property="og:locale" content="{{ app()->getLocale() === 'ru' ? 'ru_RU' : 'en_US' }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        @isset($meta['description'])
            <meta property="og:description" content="{{ $meta['description'] }}">
        @endisset
        <meta property="og:url" content="{{ $meta['canonical'] ?? url()->current() }}">
        @if (isset($meta['image']))
            <meta property="og:image" content="{{ $meta['image'] }}">
        @else
            <meta property="og:image" content="{{ asset('images/og/default.jpg') }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
        @endif
        <meta name="twitter:card" content="summary_large_image">

        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#ffffff">

        @fonts(['nunito', 'fira-sans-condensed'])

        @vite(['resources/css/shop.css', 'resources/js/shop.js'])

        @foreach ($jsonLd as $data)
            <script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endforeach
    </head>
    <body>
        @php($cartCount = app(\App\Services\Cart\Cart::class)->count())
        <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

        <p class="announcement">{{ __('Roasted to order in Ekaterinburg · delivery across Russia') }}</p>

        <header class="site-header">
            <div class="container site-header__inner">
                <a class="brand" href="{{ route('home') }}">
                    <img src="/images/shop/logo.png" alt="" width="40" height="40" class="brand__logo">
                    <span>{{ config('shop.name') }}</span>
                </a>

                <nav class="site-nav site-nav--catalog" aria-label="{{ __('Catalog') }}">
                    @foreach (\App\Support\Navigation::roots() as $root)
                        <a href="{{ url($root->path()) }}" @if (request()->is($root->slug, "{$root->slug}/*")) aria-current="page" @endif>{{ $root->name }}</a>
                    @endforeach
                </nav>

                <nav class="site-nav site-nav--account" aria-label="{{ __('Account') }}">
                    @auth
                        @can('access-admin')
                            <a href="{{ route('admin.dashboard') }}" class="site-nav__small">{{ __('Admin') }}</a>
                        @endcan
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="link-button site-nav__small">{{ __('Log out') }}</button>
                        </form>
                    @endauth
                    <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="site-nav__icon" @if (request()->routeIs('account.*')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3.5"/><path d="M5.5 19c1.4-2.6 3.8-4 6.5-4s5.1 1.4 6.5 4"/></svg>
                        <span class="visually-hidden">{{ auth()->check() ? __('My account') : __('Log in') }}</span>
                    </a>
                    <a href="{{ route('cart.show') }}" class="site-nav__icon cart-toggle" data-cart-open>
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.2 11.2a1.5 1.5 0 0 0 1.5 1.3h8.6a1.5 1.5 0 0 0 1.5-1.2L20.5 8H6.2"/><circle cx="9.5" cy="20" r="1.2"/><circle cx="17" cy="20" r="1.2"/></svg>
                        <span class="visually-hidden">{{ __('Cart') }}</span>
                        <span class="cart-toggle__count" data-cart-count @if ($cartCount === 0) hidden @endif>{{ $cartCount }}</span>
                    </a>
                </nav>
            </div>
        </header>

        <main id="main" {{ $attributes->class('site-main') }}>
            @if (session('status'))
                <div class="container">
                    <p class="notice" role="status">{{ session('status') }}</p>
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="site-footer">
            <div class="container">
                <div class="site-footer__columns">
                    <div class="site-footer__col">
                        <h2 class="site-footer__title">{{ __('Shop') }}</h2>
                        @foreach (\App\Support\Navigation::roots() as $root)
                            <a href="{{ url($root->path()) }}">{{ $root->name }}</a>
                        @endforeach
                        <a href="{{ route('cart.show') }}">{{ __('Cart') }}</a>
                    </div>
                    <div class="site-footer__col">
                        <h2 class="site-footer__title">{{ __('Support') }}</h2>
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a>
                        <a href="mailto:{{ config('shop.email') }}">{{ config('shop.email') }}</a>
                        @auth
                            <a href="{{ route('account.index') }}">{{ __('My account') }}</a>
                        @else
                            <a href="{{ route('login') }}">{{ __('Log in') }}</a>
                        @endauth
                    </div>
                    <div class="site-footer__col">
                        <h2 class="site-footer__title">{{ __('Company') }}</h2>
                        <p>{{ __('Roasted in Ekaterinburg, delivered across Russia.') }}</p>
                        <a href="{{ config('shop.vk') }}" rel="noopener">{{ __('VK community') }}</a>
                    </div>
                </div>
                <div class="site-footer__bottom">
                    <p class="site-footer__legal">&copy; {{ date('Y') }} {{ config('shop.legal_name') }}</p>
                    <nav class="site-footer__links" aria-label="{{ __('Legal information') }}">
                        <a href="{{ route('legal.terms') }}" @if (request()->routeIs('legal.terms')) aria-current="page" @endif>{{ __('Terms of use') }}</a>
                        <a href="{{ route('legal.privacy') }}" @if (request()->routeIs('legal.privacy')) aria-current="page" @endif>{{ __('Privacy policy') }}</a>
                    </nav>
                </div>
            </div>
        </footer>

        @unless (\App\Support\CookieConsent::given(request()))
            <section class="cookie-banner" aria-labelledby="cookie-banner-title" data-cookie-banner>
                <div class="cookie-banner__text">
                    <h2 id="cookie-banner-title" class="cookie-banner__title">{{ __('We use cookies') }}</h2>
                    <p>
                        {{ __('They keep you signed in and remember your cart. Analytics cookies are used only with your consent.') }}
                        <a href="{{ route('legal.privacy') }}#cookies">{{ __('Privacy policy') }}</a>
                    </p>
                </div>
                <form method="POST" action="{{ route('cookie-consent') }}" class="cookie-banner__actions" data-cookie-form>
                    @csrf
                    <button type="submit" name="choice" value="{{ \App\Support\CookieConsent::NECESSARY }}" class="button button--secondary">{{ __('Necessary only') }}</button>
                    <button type="submit" name="choice" value="{{ \App\Support\CookieConsent::ALL }}" class="button">{{ __('Accept') }}</button>
                </form>
            </section>
        @endunless

        @unless (request()->routeIs('cart.show'))
        <dialog class="drawer" id="cart" aria-labelledby="cart-title" tabindex="-1" autofocus data-cart>
            <div class="drawer__panel">
                <header class="drawer__header">
                    <h2 id="cart-title" class="drawer__title">{{ __('Cart') }}</h2>
                    <button type="button" class="drawer__close" data-cart-close>
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        <span class="visually-hidden">{{ __('Close') }}</span>
                    </button>
                </header>
                <div class="drawer__body cart" data-cart-body>
                    @include('shop.cart.contents', ['cart' => app(\App\Services\Cart\Cart::class)])
                </div>
            </div>
        </dialog>
        @endunless
    </body>
</html>
