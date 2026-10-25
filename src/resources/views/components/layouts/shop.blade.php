@props(['title' => null, 'meta' => [], 'jsonLd' => []])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $meta['title'] ?? (($title ? $title.' — ' : '').config('app.name')) }}</title>
        @isset($meta['description'])
            <meta name="description" content="{{ $meta['description'] }}">
        @endisset
        @isset($meta['robots'])
            <meta name="robots" content="{{ $meta['robots'] }}">
        @endisset
        @isset($meta['canonical'])
            <link rel="canonical" href="{{ $meta['canonical'] }}">
        @endisset

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts(['nunito', 'fira-sans-condensed'])

        @vite(['resources/css/shop.css', 'resources/js/shop.js'])

        @foreach ($jsonLd as $data)
            <script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endforeach
    </head>
    <body>
        <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

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
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3.5"/><path d="M5.5 19c1.4-2.6 3.8-4 6.5-4s5.1 1.4 6.5 4"/></svg>
                        <span class="visually-hidden">{{ auth()->check() ? __('My account') : __('Log in') }}</span>
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
            <div class="container site-footer__inner">
                <div class="site-footer__col">
                    <h2 class="site-footer__title">{{ __('Shop') }}</h2>
                    @foreach (\App\Support\Navigation::roots() as $root)
                        <a href="{{ url($root->path()) }}">{{ $root->name }}</a>
                    @endforeach
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
                <div class="site-footer__col site-footer__col--brand">
                    <a class="brand" href="{{ route('home') }}">
                        <img src="/images/shop/logo.png" alt="" width="40" height="40" class="brand__logo">
                        <span>{{ config('shop.name') }}</span>
                    </a>
                    <p>{{ __('Roasted in Ekaterinburg, delivered across Russia.') }}</p>
                    <a href="{{ config('shop.vk') }}" rel="noopener">{{ __('VK community') }}</a>
                </div>
                <p class="site-footer__legal">&copy; {{ date('Y') }} {{ config('shop.legal_name') }}</p>
            </div>
            <img src="/images/shop/footer-plant.svg" alt="" class="site-footer__plant" width="210" height="210" loading="lazy">
        </footer>
    </body>
</html>
