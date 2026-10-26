<x-layouts.shop :meta="[
    'title' => __('Coffee Project: freshly roasted coffee with delivery across Russia'),
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
        @php
            // What each method does to the cup, in a line.
            $taglines = [
                'espresso' => 'Плотное тело и сладость, в чашке и с молоком',
                'pourover' => 'Чистый, яркий вкус с фруктовыми нотами',
                'chemex' => 'Лёгкий и прозрачный, раскрывает кислинку',
                'aeropress' => 'Быстро и насыщенно, дома и в дороге',
                'drip' => 'Просто и стабильно, на всю семью',
                'french' => 'Плотный и бархатистый, без лишних хлопот',
                'cezve' => 'Крепкий и ароматный, по-домашнему',
                'geyzer' => 'Насыщенный, почти как эспрессо, на плите',
            ];
        @endphp
        <section class="brew-picker" aria-labelledby="brew-picker-title">
            <div class="container">
                <header class="brew-picker__header">
                    <div class="brew-picker__intro">
                        <p class="brew-picker__eyebrow">{{ __('Pick by brew method') }}</p>
                        <h2 id="brew-picker-title">{{ __('Coffee for your brew method') }}</h2>
                        <p class="brew-picker__lead">{{ __('Tell us how you brew, and we will show the coffees that open up best in it. We grind for your method for free.') }}</p>
                    </div>
                    <a href="{{ url($coffee->path()) }}" class="button button--large brew-picker__all">
                        {{ __('All coffee') }}
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </header>

                <ul class="brew-picker__grid">
                    @foreach ($brewMethods as ['method' => $method, 'count' => $count, 'from' => $from])
                        <li>
                            <a href="{{ url("/{$coffee->slug}/{$method->slug}") }}" class="brew-card">
                                <span class="brew-card__icon">
                                    @if (file_exists(public_path("images/brew/{$method->slug}.svg")))
                                        <img src="/images/brew/{{ $method->slug }}.svg" alt="" width="32" height="32" loading="lazy">
                                    @endif
                                </span>
                                <span class="brew-card__name">{{ $method->name }}</span>
                                @isset($taglines[$method->slug])
                                    <span class="brew-card__tagline">{{ $taglines[$method->slug] }}</span>
                                @endisset
                                <span class="brew-card__meta">
                                    <span>
                                        {{ trans_choice(':count coffee|:count coffees', $count) }}@if ($from) · {{ __('from :price', ['price' => \App\Support\Money::rub($from)]) }}@endif
                                    </span>
                                    <span class="brew-card__arrow" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <ul class="perks">
                    <li class="perk">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c2.5 3 4 5.5 4 8a4 4 0 0 1-8 0c0-2.5 1.5-5 4-8Z"/><path d="M8 21h8"/></svg>
                        <span><strong>{{ __('Roasted to order') }}</strong> {{ __('We ship the next working day after your order is confirmed.') }}</span>
                    </li>
                    <li class="perk">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2.5"/></svg>
                        <span><strong>{{ __('Ground for your method') }}</strong> {{ __('Free of charge, or in whole beans if you grind yourself.') }}</span>
                    </li>
                    <li class="perk">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>
                        <span><strong>{{ __('Delivery across Russia') }}</strong> {{ __('By courier or to a pick-up point near you.') }}</span>
                    </li>
                </ul>
            </div>
        </section>
    @endif

    <section class="container home-quiz" aria-labelledby="home-quiz-title">
        <div class="home-quiz__inner">
            <div>
                <h2 id="home-quiz-title">{{ __('Not sure where to start?') }}</h2>
                <p>{{ __('Answer five short questions about how you brew and what you like, and we will pick a coffee for you.') }}</p>
            </div>
            <a href="{{ route('quiz') }}" class="button button--large">{{ __('Find my coffee') }}</a>
        </div>
    </section>
</x-layouts.shop>
