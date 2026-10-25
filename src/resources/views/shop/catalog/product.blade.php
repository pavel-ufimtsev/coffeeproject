@php
    use App\Support\Money;
    $variants = $product->activeVariants;
    $selected = $variants->first(fn ($v) => (float) $v->price > 0) ?? $variants->first();
    $purchasable = $product->isPurchasable();
    $specs = $product->specList();
@endphp
<x-layouts.shop :meta="$meta" :json-ld="$jsonLd">
    <div class="container product-page">
        <x-catalog.breadcrumbs :items="$breadcrumbs" />

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
                @if ($product->tagline)
                    <p class="product__tagline">{{ $product->tagline }}</p>
                @endif
                <h1 class="product__name">{{ $product->name }}</h1>
                @if ($product->roaster)
                    <p class="product__by">{{ __('Roaster') }}: {{ $product->roaster->name }}, {{ $product->roaster->city }}</p>
                @endif

                @if ($product->summary)
                    <p class="product__summary">{!! nl2br(e($product->summary)) !!}</p>
                @endif

                @if ($purchasable)
                    <form class="buy" data-buy onsubmit="return false">
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
                                <select id="grind" name="grind">
                                    @foreach ($grinds as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <button type="submit" class="button button--block" disabled>{{ __('Online ordering opens soon') }}</button>
                    </form>
                @elseif (! $isArchived)
                    <p class="notice notice--warning">{{ __('Not available right now.') }}</p>
                @endif

                @if ($shipsFrom->isNotEmpty() && ! $isArchived)
                    <ul class="product__facts">
                        @foreach ($shipsFrom as $location)
                            <li>{{ __('Ships from :city', ['city' => $location->city]) }}</li>
                            @if ($location->roast_days && in_array($product->type, [\App\Enums\ProductType::Coffee, \App\Enums\ProductType::Drip], true))
                                <li>{{ __('Roasted every :days', ['days' => collect($location->roast_days)->map(fn ($day) => \Illuminate\Support\Carbon::parse($day)->locale(app()->getLocale())->isoFormat('dd'))->implode(', ')]) }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif

                @if ($landingTags->isNotEmpty())
                    <ul class="chips chips--small" aria-label="{{ __('Find similar') }}">
                        @foreach ($landingTags as $tag)
                            <li><a href="{{ url("/{$root->slug}/{$tag->slug}") }}" class="chip">{{ $tag->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        @if ($specs !== [])
            <section class="product-section">
                <h2>{{ __('About this coffee') }}</h2>
                <dl class="specs">
                    @foreach ($specs as $spec)
                        <div>
                            <dt>{{ $spec['label'] }}</dt>
                            <dd>{{ $spec['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($product->description)
            <section class="product-section prose">
                <h2>{{ in_array($product->type, [\App\Enums\ProductType::Coffee, \App\Enums\ProductType::Drip], true) ? __("Roaster's notes") : __('Description') }}</h2>
                {!! $product->description !!}
            </section>
        @endif

        @if ($alternatives->isNotEmpty())
            <section class="product-section">
                <h2>{{ $isArchived ? __('Instead of this product') : __('You may also like') }}</h2>
                <div class="product-grid">
                    @foreach ($alternatives as $alternative)
                        <x-catalog.product-card :product="$alternative" :heading-level="3" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.shop>
