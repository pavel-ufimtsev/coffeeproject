<x-layouts.auth :title="__('Log in')" :description="__('Enter your email and password to log in.')">
    @if ($status)
        <p class="notice" role="status">{{ $status }}</p>
    @endif

    {{-- @chisel-passkeys --}}
    <div class="passkey" data-passkey-login hidden>
        <button type="button" class="button button--secondary button--block">{{ __('Log in with a passkey') }}</button>
        <p class="field__error" data-passkey-error hidden></p>
        <p class="divider"><span>{{ __('or use your email') }}</span></p>
    </div>
    {{-- @end-chisel-passkeys --}}

    <form method="POST" action="{{ route('login.store') }}" class="stack">
        @csrf

        <x-form.input name="email" type="email" :label="__('Email address')" required autofocus autocomplete="email webauthn" />

        <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="current-password" />

        <div class="row row--between">
            <label class="checkbox">
                <input type="checkbox" name="remember" @checked(old('remember'))>
                {{ __('Remember me') }}
            </label>

            @if ($canResetPassword)
                <a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            @endif
        </div>

        <button type="submit" class="button button--block">{{ __('Log in') }}</button>
    </form>

    @if ($canRegister)
        <x-slot:footer>
            {{ __('New here?') }} <a href="{{ route('register') }}">{{ __('Create an account') }}</a>
        </x-slot:footer>
    @endif
</x-layouts.auth>
