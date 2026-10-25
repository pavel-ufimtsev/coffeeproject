<x-layouts.account :title="__('My account')">
    <p>{{ __('Hello, :name.', ['name' => $user->name]) }}</p>

    <section class="card">
        <h2>{{ __('Orders') }}</h2>
        <p class="muted">{{ __('You have not placed any orders yet.') }}</p>
        <p><a class="button button--secondary" href="{{ route('home') }}">{{ __('Browse coffee') }}</a></p>
    </section>
</x-layouts.account>
