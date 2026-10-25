<x-layouts.shop>
    <section class="hero">
        <div class="container">
            <h1>{{ __('Freshly roasted coffee, delivered') }}</h1>
            <p class="hero__lead">{{ __('The catalog is on its way. In the meantime you can create an account.') }}</p>
            @guest
                <p><a class="button" href="{{ route('register') }}">{{ __('Create account') }}</a></p>
            @endguest
        </div>
    </section>
</x-layouts.shop>
