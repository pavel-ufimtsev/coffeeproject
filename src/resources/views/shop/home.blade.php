<x-layouts.shop :meta="[
    'title' => __('Coffee Project — freshly roasted coffee with delivery across Russia'),
    'description' => __('Specialty coffee roasted to order in Ekaterinburg. Coffee beans, drip bags and tea with delivery across Russia.'),
    'canonical' => route('home'),
]" class="site-main--flush">
    <section class="splash">
        <div class="container splash__text">
            <h1 class="splash__title">{{ __('Freshly roasted coffee, delivered') }}</h1>
            <p class="splash__lead">{{ __('We roast to order in Ekaterinburg and ship across Russia.') }}</p>
            <p class="splash__actions">
                @foreach ($roots as $root)
                    <a class="splash__button" href="{{ url($root->path()) }}">{{ $root->is($coffee) ? __('Choose coffee') : $root->name }}</a>
                @endforeach
            </p>
        </div>
        @foreach (range(1, 5) as $i)
            <img src="/images/shop/splash-{{ $i }}.webp" alt="" class="splash__pack splash__pack--{{ $i }}" @if ($i > 2) loading="lazy" @endif>
        @endforeach
    </section>

    @if ($featured->isNotEmpty())
        <section class="container home-section">
            <header class="home-section__header">
                <h2>{{ __('Recommended!') }}</h2>
                <p class="muted">{{ __('Best sellers and popular coffees') }}</p>
            </header>
            <div class="product-grid">
                @foreach ($featured as $product)
                    <x-catalog.product-card :product="$product" :heading-level="3" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($brewMethods->isNotEmpty())
        <section class="brew-band">
            <div class="container brew-band__inner">
                <h2 class="brew-band__title">{{ __('Coffee for your brew method') }}</h2>
                <ul class="brew-band__list">
                    @foreach ($brewMethods as $method)
                        <li>
                            <a href="{{ url("/{$coffee->slug}/{$method->slug}") }}" class="brew-tile">
                                @if (file_exists(public_path("images/brew/{$method->slug}.svg")))
                                    <img src="/images/brew/{{ $method->slug }}.svg" alt="" width="56" height="56" class="brew-tile__icon" loading="lazy">
                                @endif
                                <span>{{ $method->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p><a href="{{ url($coffee->path()) }}" class="band-button">{{ __('All coffee') }}</a></p>
            </div>
        </section>
    @endif
</x-layouts.shop>
