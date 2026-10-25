<x-layouts.auth :title="__('Forgot your password?')" :description="__('Enter your email and we will send you a link to choose a new one.')">
    @if ($status)
        <p class="notice" role="status">{{ $status }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="stack">
        @csrf

        <x-form.input name="email" type="email" :label="__('Email address')" required autofocus autocomplete="email" />

        <button type="submit" class="button button--block">{{ __('Email me a reset link') }}</button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}">{{ __('Back to log in') }}</a>
    </x-slot:footer>
</x-layouts.auth>
