<x-layouts.auth :title="__('Confirm your password')" :description="__('This is a secure area. Please confirm your password to continue.')">
    <form method="POST" action="{{ route('password.confirm.store') }}" class="stack">
        @csrf

        <x-form.input name="password" type="password" :label="__('Password')" required autofocus autocomplete="current-password" />

        <button type="submit" class="button button--block">{{ __('Confirm') }}</button>
    </form>
</x-layouts.auth>
