@props(['title'])
<x-layouts.shop :title="$title">
    <div class="container account">
        <nav class="account__nav" aria-label="{{ __('My account') }}">
            <a href="{{ route('account.index') }}" @if (request()->routeIs('account.index')) aria-current="page" @endif>{{ __('Overview') }}</a>
            <a href="{{ route('account.settings') }}" @if (request()->routeIs('account.settings')) aria-current="page" @endif>{{ __('Settings') }}</a>
        </nav>

        <div class="account__content">
            <h1>{{ $title }}</h1>

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="notice notice--warning">
                    <p>{{ __('Please confirm your email address. We sent you a link when you signed up.') }}</p>
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="link-button">{{ __('Send the link again') }}</button>
                    </form>
                </div>
            @endif

            {{ $slot }}
        </div>
    </div>
</x-layouts.shop>
