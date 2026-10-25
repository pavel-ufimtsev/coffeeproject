@props(['title'])
{{-- Checkout and thank-you pages: logo and cart only, nothing to wander off to. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex,nofollow">

        <title>{{ $title }} — {{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts(['nunito', 'fira-sans-condensed'])

        @vite(['resources/css/shop.css', 'resources/js/shop.js'])
    </head>
    <body class="checkout-body">
        <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

        <header class="checkout-header">
            <div class="checkout-header__inner">
                <a class="brand" href="{{ route('home') }}">
                    <img src="/images/shop/logo.png" alt="" width="40" height="40" class="brand__logo">
                    <span>{{ config('shop.name') }}</span>
                </a>
                <a href="{{ route('cart.show') }}" class="checkout-header__cart">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.2 11.2a1.5 1.5 0 0 0 1.5 1.3h8.6a1.5 1.5 0 0 0 1.5-1.2L20.5 8H6.2"/><circle cx="9.5" cy="20" r="1.2"/><circle cx="17" cy="20" r="1.2"/></svg>
                    <span class="visually-hidden">{{ __('Back to cart') }}</span>
                </a>
            </div>
        </header>

        <main id="main" class="checkout-page">
            {{ $slot }}
        </main>
    </body>
</html>
