<x-layouts.auth :title="__('Create an account')" :description="__('Track your orders and check out faster next time.')">
    <form method="POST" action="{{ route('register.store') }}" class="stack">
        @csrf

        <x-form.input name="name" :label="__('Name')" required autofocus autocomplete="name" />

        <x-form.input name="email" type="email" :label="__('Email address')" :value="request('email')" required autocomplete="email" />

        <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="new-password" :passwordrules="$passwordRules" />

        <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />

        {{-- Honeypot: people never see it, bots fill it in. --}}
        <div class="contact-form__trap" aria-hidden="true">
            <label for="register-website">Website</label>
            <input type="text" id="register-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="button button--block">{{ __('Create account') }}</button>
    </form>

    <x-slot:footer>
        {{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a>
    </x-slot:footer>
</x-layouts.auth>
