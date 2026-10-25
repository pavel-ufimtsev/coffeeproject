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

        @fonts

        @vite(['resources/css/shop.css', 'resources/js/shop.js'])

        @foreach ($jsonLd as $data)
            <script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endforeach
    </head>
    <body>
        <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

        <header class="site-header">
            <div class="container site-header__inner">
                <a class="brand" href="{{ route('home') }}">{{ config('app.name') }}</a>

                <nav class="site-nav site-nav--catalog" aria-label="{{ __('Catalog') }}">
                    @foreach (\App\Support\Navigation::roots() as $root)
                        <a href="{{ url($root->path()) }}" @if (request()->is($root->slug, "{$root->slug}/*")) aria-current="page" @endif>{{ $root->name }}</a>
                    @endforeach
                </nav>

                <nav class="site-nav" aria-label="{{ __('Account') }}">
                    @auth
                        @can('access-admin')
                            <a href="{{ route('admin.dashboard') }}">{{ __('Admin') }}</a>
                        @endcan
                        <a href="{{ route('account.index') }}" @if (request()->routeIs('account.*')) aria-current="page" @endif>{{ __('My account') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="link-button">{{ __('Log out') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}">{{ __('Log in') }}</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="button button--small">{{ __('Create account') }}</a>
                        @endif
                    @endauth
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
                <nav class="footer-nav" aria-label="{{ __('Catalog') }}">
                    @foreach (\App\Support\Navigation::roots() as $root)
                        <a href="{{ url($root->path()) }}">{{ $root->name }}</a>
                    @endforeach
                </nav>
                <p>{{ __('Roasted in Ekaterinburg, delivered across Russia.') }}</p>
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
            </div>
        </footer>
    </body>
</html>
