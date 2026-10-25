<x-layouts.account :title="__('Settings')">
    <section class="card">
        <h2>{{ __('Profile') }}</h2>

        <form method="POST" action="{{ route('account.profile.update') }}" class="stack">
            @csrf
            @method('PATCH')

            <x-form.input name="name" bag="profile" :label="__('Name')" :value="$user->name" required autocomplete="name" />

            <x-form.input name="email" bag="profile" type="email" :label="__('Email address')" :value="$user->email" required autocomplete="email"
                :hint="__('Changing your email means confirming the new address.')" />

            <div><button type="submit" class="button">{{ __('Save') }}</button></div>
        </form>
    </section>

    <section class="card">
        <h2>{{ __('Password') }}</h2>

        <form method="POST" action="{{ route('account.password.update') }}" class="stack">
            @csrf
            @method('PUT')

            <x-form.input name="current_password" bag="password" type="password" :label="__('Current password')" required autocomplete="current-password" />

            <x-form.input name="password" bag="password" type="password" :label="__('New password')" required autocomplete="new-password" />

            <x-form.input name="password_confirmation" bag="password" type="password" :label="__('Confirm new password')" required autocomplete="new-password" />

            <div><button type="submit" class="button">{{ __('Update password') }}</button></div>
        </form>
    </section>

    @unless ($user->isAdmin())
        <section class="card card--danger">
            <h2>{{ __('Delete account') }}</h2>
            <p class="muted">{{ __('Your account and saved details will be removed permanently.') }}</p>

            <details @if ($errors->deleteAccount->any()) open @endif>
                <summary class="button button--danger">{{ __('Delete my account') }}</summary>

                <form method="POST" action="{{ route('account.destroy') }}" class="stack">
                    @csrf
                    @method('DELETE')

                    <x-form.input name="password" bag="deleteAccount" type="password" :label="__('Enter your password to confirm')" required autocomplete="current-password" />

                    <div><button type="submit" class="button button--danger">{{ __('Delete permanently') }}</button></div>
                </form>
            </details>
        </section>
    @endunless
</x-layouts.account>
