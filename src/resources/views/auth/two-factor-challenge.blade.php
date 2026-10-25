<x-layouts.auth :title="__('Two-factor authentication')">
    @php($useRecovery = $errors->has('recovery_code'))

    <div data-two-factor>
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="stack" data-two-factor-code @if ($useRecovery) hidden @endif>
            @csrf
            <p class="muted">{{ __('Enter the code from your authenticator app.') }}</p>

            <x-form.input name="code" :label="__('Authentication code')" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" :autofocus="! $useRecovery" />

            <button type="submit" class="button button--block">{{ __('Continue') }}</button>
            <button type="button" class="link-button" data-two-factor-toggle>{{ __('Use a recovery code instead') }}</button>
        </form>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="stack" data-two-factor-recovery @unless ($useRecovery) hidden @endunless>
            @csrf
            <p class="muted">{{ __('Enter one of your emergency recovery codes.') }}</p>

            <x-form.input name="recovery_code" :label="__('Recovery code')" autocomplete="off" :autofocus="$useRecovery" />

            <button type="submit" class="button button--block">{{ __('Continue') }}</button>
            <button type="button" class="link-button" data-two-factor-toggle>{{ __('Use an authentication code instead') }}</button>
        </form>
    </div>
</x-layouts.auth>
