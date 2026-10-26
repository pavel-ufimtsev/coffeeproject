@php
    use App\Support\Money;
    $variants = $product->activeVariants;
    $selected = $variants->first(fn ($v) => (float) $v->price > 0) ?? $variants->first();
    $purchasable = $product->isPurchasable();
    $specs = $product->specList();
    $isCoffee = in_array($product->type, [\App\Enums\ProductType::Coffee, \App\Enums\ProductType::Drip], true);
    // Taste sits at the top; the rest of the facts describe where it comes from.
    $origin = \Illuminate\Support\Arr::except($specs, ['roast', 'sca_score', 'tasting_notes']);
    $notes = $product->tastingNotes();
    $roastLevel = $product->roastLevel();
    $summary = preg_split('/\R+/u', trim((string) $product->summary), 2);
    $roastDays = $shipsFrom->pluck('roast_days')->filter()->flatten()->unique()
        ->sortBy(fn ($day) => (\Illuminate\Support\Carbon::parse($day)->dayOfWeekIso))
        ->map(fn ($day) => \Illuminate\Support\Carbon::parse($day)->locale(app()->getLocale())->isoFormat('dd'))
        ->implode(', ');
@endphp
<x-layouts.shop :meta="$meta" :json-ld="$jsonLd">
    <div class="container product-page">
        <x-catalog.breadcrumbs :items="$breadcrumbs" />

        @if ($isDraft)
            <p class="notice notice--warning" role="status">{{ __('Draft preview: only admins can see this page.') }}</p>
        @endif
        @if ($isArchived)
            <p class="notice notice--warning" role="status">{{ __('This product is no longer sold. Have a look at the alternatives below.') }}</p>
        @endif

        <div class="product">
            <div class="product__media">
                @if ($product->images->isNotEmpty())
                    <img src="{{ $product->images->first()->url() }}" alt="{{ $product->images->first()->alt ?? $product->name }}" width="1200" height="1200" class="product__image">
                @else
                    <span class="product__image product-card__image--empty" aria-hidden="true"></span>
                @endif
            </div>

            <div class="product__info">
                <header class="product__head">
                    @if ($product->tagline)
                        <p class="product__tagline">{{ $product->tagline }}</p>
                    @endif
                    <h1 class="product__name">{{ $product->name }}</h1>
                    @if ($product->roaster)
                        <p class="product__by">{{ __('Roaster') }}: {{ $product->roaster->name }}, {{ $product->roaster->city }}</p>
                    @endif
                </header>

                @if ($isCoffee && (filled($specs['tasting_notes'] ?? null) || $roastLevel || filled($specs['sca_score'] ?? null)))
                    <div class="taste">
                        @if ($notes)
                            <ul class="taste__notes" aria-label="{{ __('Tasting notes') }}">
                                @foreach ($notes as $note)
                                    <li>{{ $note }}</li>
                                @endforeach
                            </ul>
                        @elseif (filled($specs['tasting_notes'] ?? null))
                            <p class="taste__text">{{ \Illuminate\Support\Str::ucfirst(preg_replace('/^во вкусе\s+/iu', '', $specs['tasting_notes']['value'])) }}</p>
                        @endif

                        @if ($roastLevel || filled($specs['sca_score'] ?? null))
                            <div class="taste__row">
                                @if ($roastLevel)
                                    <p class="roast">
                                        <span class="roast__scale" role="img" aria-label="{{ __(':roast, :level of 5', ['roast' => $specs['roast']['value'], 'level' => $roastLevel]) }}">
                                            @foreach (range(1, 5) as $step)
                                                <span @class(['is-on' => $step <= $roastLevel])></span>
                                            @endforeach
                                        </span>
                                        <span class="roast__text" aria-hidden="true">{{ $specs['roast']['value'] }}</span>
                                    </p>
                                @endif
                                @if (filled($specs['sca_score'] ?? null))
                                    <p class="sca" title="{{ __('Cup score by the Specialty Coffee Association scale; specialty starts at 80.') }}">SCA&nbsp;{{ preg_match('/\d+(?:[.,]\d+)?\+?/', $specs['sca_score']['value'], $score) ? $score[0] : $specs['sca_score']['value'] }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                @if ($summary[0] !== '')
                    <div class="product__summary">
                        <p>{{ $summary[0] }}</p>
                        @isset($summary[1])
                            <p class="product__summary-more">{!! nl2br(e($summary[1])) !!}</p>
                        @endisset
                    </div>
                @endif

                @if ($brewTags->isNotEmpty())
                    <p class="product__brew">
                        <span>{{ __('Good for') }}:</span>
                        @foreach ($brewTags as $tag)
                            <a href="{{ url("/{$root->slug}/{$tag->slug}") }}">{{ $tag->name }}</a>@unless ($loop->last)<span aria-hidden="true">·</span>@endunless
                        @endforeach
                    </p>
                @endif

                @if ($purchasable)
                    <form method="POST" action="{{ route('cart.store') }}" class="buy" data-buy data-add-to-cart data-price-scope>
                        @csrf
                        <p class="buy__price">
                            <strong data-price>{{ Money::rub($selected->price) }}</strong>
                            <s data-old-price @unless ($selected->isDiscounted()) hidden @endunless>{{ $selected->isDiscounted() ? Money::rub($selected->old_price) : '' }}</s>
                        </p>

                        @if ($variants->count() > 1)
                            <fieldset class="buy__variants">
                                <legend>{{ __('Size') }}</legend>
                                @foreach ($variants as $variant)
                                    <label class="variant">
                                        <input type="radio" name="variant" value="{{ $variant->id }}" @checked($variant->is($selected))
                                            data-price="{{ Money::rub($variant->price) }}"
                                            data-old-price="{{ $variant->isDiscounted() ? Money::rub($variant->old_price) : '' }}">
                                        <span>{{ $variant->label }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @else
                            <p class="muted">{{ $selected->label }}</p>
                            <input type="hidden" name="variant" value="{{ $selected->id }}">
                        @endif

                        @if ($grinds)
                            <div class="field">
                                <label for="grind">{{ __('Grind') }}</label>
                                <span class="select">
                                    <select id="grind" name="grind">
                                        @foreach ($grinds as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </span>
                            </div>
                        @endif

                        <button type="submit" class="button button--block button--large add-button" data-done-label="{{ __('Added') }}">
                            <span class="add-button__label">{{ __('Add to cart') }}</span>
                        </button>
                    </form>

                    <ul class="assurances">
                        @if ($isCoffee && $roastDays !== '')
                            <li>
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c1 3.6 5.5 5.6 5.5 10.5a5.5 5.5 0 0 1-11 0c0-2.6 1.4-4.3 2.6-5.4.4 1.9 1.4 2.9 2.4 2.9 0-3.2-.9-5.2.5-8Z"/></svg>
                                <span>{{ __('Roasted to order on :days, shipped from :city', ['days' => $roastDays, 'city' => $shipsFrom->first()->city]) }}</span>
                            </li>
                        @elseif ($shipsFrom->isNotEmpty())
                            <li>
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.1-7-11.5a7 7 0 0 1 14 0C19 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                                <span>{{ __('Ships from :city', ['city' => $shipsFrom->first()->city]) }}</span>
                            </li>
                        @endif
                        @if ($delivery['courier'])
                            <li>
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6.5h11v9H3zM14 9.5h3.5l3 3v3H14"/><circle cx="7" cy="17.5" r="1.75"/><circle cx="17" cy="17.5" r="1.75"/></svg>
                                <span>
                                    {{ __('Courier in :city', ['city' => config('shop.courier_city')]) }}@if ($delivery['courier_free_from']), {{ __('free from :sum', ['sum' => Money::rub($delivery['courier_free_from'])]) }}@endif
                                </span>
                            </li>
                        @endif
                        @if ($delivery['cdek'])
                            <li>
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 8 12 4l8.5 4v8L12 20l-8.5-4Z"/><path d="M3.5 8 12 12l8.5-4M12 12v8"/></svg>
                                <span>
                                    {{ __('CDEK pickup points across Russia') }}@if ($delivery['cdek_free_from']), {{ __('free from :sum', ['sum' => Money::rub($delivery['cdek_free_from'])]) }}@endif
                                </span>
                            </li>
                        @endif
                    </ul>
                @elseif (! $isArchived)
                    <p class="notice notice--warning">{{ __('Not available right now.') }}</p>
                @endif

                @if ($origin !== [])
                    <section class="origin" aria-labelledby="origin-title">
                        <h2 id="origin-title" class="origin__title">{{ __('Origin') }}</h2>
                        <dl class="specs">
                            @foreach ($origin as $key => $spec)
                                <div class="spec">
                                    <dt><span class="spec__icon"><x-catalog.spec-icon :name="$key" /></span>{{ $spec['label'] }}</dt>
                                    <dd>{{ $spec['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif
            </div>
        </div>

        @if ($product->description)
            <section class="product-section product-notes" data-collapsible data-more-label="{{ __('Read in full') }}" data-less-label="{{ __('Show less') }}">
                <h2>{{ $isCoffee ? __("Roaster's notes") : __('Description') }}</h2>
                <div class="prose product-notes__body" id="product-notes" data-collapsible-body>
                    {!! $product->description !!}
                </div>
            </section>
        @endif

        @if ($alternatives->isNotEmpty())
            <section class="product-section">
                <h2>{{ $isArchived ? __('Instead of this product') : __('You may also like') }}</h2>
                @if ($landingTags->isNotEmpty())
                    <ul class="chips chips--small" aria-label="{{ __('Find similar') }}">
                        @foreach ($landingTags as $tag)
                            <li><a href="{{ url("/{$root->slug}/{$tag->slug}") }}" class="chip">{{ $tag->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
                <div class="product-grid">
                    @foreach ($alternatives as $alternative)
                        <x-catalog.product-card :product="$alternative" :heading-level="3" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.shop>
