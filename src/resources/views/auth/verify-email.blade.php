<x-layouts.auth :title="__('Confirm your email')" :description="__('We sent a link to your email address. Open it to confirm your account.')">
    @if ($status === 'verification-link-sent')
        <p class="notice" role="status">{{ __('A new link has been sent to your email address.') }}</p>
    @endif

    <div class="stack">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="button button--block">{{ __('Send the link again') }}</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link-button">{{ __('Log out') }}</button>
        </form>
    </div>
</x-layouts.auth>
