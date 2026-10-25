<x-layouts.shop :meta="[
    'title' => __('Coffee Project — freshly roasted coffee with delivery across Russia'),
    'description' => __('Specialty coffee roasted to order in Ekaterinburg. Coffee beans, drip bags and tea with delivery across Russia.'),
    'canonical' => route('home'),
]">
    <section class="hero">
        <div class="container">
            <h1>{{ __('Freshly roasted coffee, delivered') }}</h1>
            <p class="hero__lead">{{ __('We roast to order in Ekaterinburg and ship across Russia.') }}</p>
            <p><a class="button" href="{{ url('coffee') }}">{{ __('Choose coffee') }}</a></p>
        </div>
    </section>

    @if ($featured->isNotEmpty())
        <section class="container home-section">
            <h2>{{ __('Recommended') }}</h2>
            <div class="product-grid">
                @foreach ($featured as $product)
                    <x-catalog.product-card :product="$product" :heading-level="3" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="container home-section">
        <h2>{{ __('Catalog') }}</h2>
        <nav class="chips" aria-label="{{ __('Catalog') }}">
            @foreach ($roots as $root)
                <a href="{{ url($root->path()) }}" class="chip">{{ $root->name }}</a>
            @endforeach
        </nav>
    </section>
</x-layouts.shop>
