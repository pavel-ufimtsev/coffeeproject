@props(['product', 'headingLevel' => 2])
@php
    use App\Enums\ProductType;
    use App\Support\Money;

    $variants = $product->activeVariants->filter(fn ($v) => (float) $v->price > 0)->values();
    $selected = $variants->first();
    $grinds = $product->type->hasGrind() ? ProductType::grinds() : [];
    $badge = match (true) {
        $variants->contains(fn ($v) => $v->isDiscounted()) => __('Sale'),
        $product->is_featured => __('Bestseller'),
        default => null,
    };
@endphp
<article class="product-card" data-price-scope>
    {{-- The whole card opens the product: the name's link is stretched over it. --}}
    <div class="product-card__media">
        @if ($product->mainImage)
            <img src="{{ $product->mainImage->thumbUrl() }}" alt="" width="600" height="600" loading="lazy" class="product-card__image">
        @endif
        @if ($badge)
            <span class="product-card__badge">{{ $badge }}</span>
        @endif
    </div>

    <div class="product-card__body">
        @if ($product->roaster)
            <p class="product-card__by">{{ $product->roaster->name }}</p>
        @endif
        <div class="product-card__title">
            <a href="{{ url($product->path()) }}" class="product-card__name" role="heading" aria-level="{{ $headingLevel }}">{{ $product->name }}</a>
            @if ($selected)
                <p class="product-card__price">
                    <span data-price>{{ Money::rub($selected->price) }}</span>
                    <s data-old-price @unless ($selected->isDiscounted()) hidden @endunless>{{ $selected->isDiscounted() ? Money::rub($selected->old_price) : '' }}</s>
                </p>
            @endif
        </div>
        @if ($product->tagline)
            <p class="product-card__tagline">{{ $product->tagline }}</p>
        @endif

        @if ($selected && $product->isPurchasable())
            <form method="POST" action="{{ route('cart.store') }}" class="product-card__buy" data-add-to-cart>
                @csrf
                @if ($variants->count() > 1)
                    <fieldset class="sizes">
                        <legend class="visually-hidden">{{ __('Size') }}</legend>
                        @foreach ($variants as $variant)
                            <label class="sizes__option">
                                <input type="radio" name="variant" value="{{ $variant->id }}" @checked($variant->is($selected))
                                    data-price="{{ Money::rub($variant->price) }}"
                                    data-old-price="{{ $variant->isDiscounted() ? Money::rub($variant->old_price) : '' }}">
                                <span>{{ $variant->label }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                @else
                    <input type="hidden" name="variant" value="{{ $selected->id }}">
                    <p class="sizes__single">{{ $selected->label }}</p>
                @endif

                @if ($grinds)
                    <label class="select">
                        <span class="visually-hidden">{{ __('Grind') }}</span>
                        <select name="grind">
                            @foreach ($grinds as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <button type="submit" class="button button--block add-button" data-done-label="{{ __('Added') }}">
                    <span class="add-button__label">{{ __('Add to cart') }}</span>
                </button>
            </form>
        @else
            <p class="product-card__unavailable">{{ __('Not available') }}</p>
        @endif
    </div>
</article>
