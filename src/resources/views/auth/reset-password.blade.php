<x-layouts.auth :title="__('Choose a new password')">
    <form method="POST" action="{{ route('password.update') }}" class="stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.input name="email" type="email" :label="__('Email address')" :value="$email" required autocomplete="email" />

        <x-form.input name="password" type="password" :label="__('New password')" required autofocus autocomplete="new-password" :passwordrules="$passwordRules" />

        <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />

        <button type="submit" class="button button--block">{{ __('Reset password') }}</button>
    </form>
</x-layouts.auth>
