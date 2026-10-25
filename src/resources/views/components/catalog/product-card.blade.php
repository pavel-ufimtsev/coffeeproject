@props(['product', 'headingLevel' => 2])
@php
    $minPrice = $product->minPrice();
    $variants = $product->activeVariants;
    $discounted = $variants->first(fn ($v) => $v->isDiscounted());
@endphp
<article class="product-card">
    <a href="{{ url($product->path()) }}" class="product-card__link">
        @if ($product->mainImage)
            <img src="{{ $product->mainImage->thumbUrl() }}" alt="" width="600" height="600" loading="lazy" class="product-card__image">
        @else
            <span class="product-card__image product-card__image--empty" aria-hidden="true"></span>
        @endif
        <span class="product-card__body">
            @if ($product->tagline)
                <span class="product-card__tagline">{{ $product->tagline }}</span>
            @endif
            <span class="product-card__name" role="heading" aria-level="{{ $headingLevel }}">{{ $product->name }}</span>
            @if ($product->roaster)
                <span class="product-card__by">{{ $product->roaster->name }}, {{ $product->roaster->city }}</span>
            @endif
            <span class="product-card__price">
                @if ($minPrice)
                    <strong>{{ $variants->count() > 1 ? __('from') : '' }} {{ \App\Support\Money::rub($minPrice) }}</strong>
                    @if ($discounted)
                        <s>{{ \App\Support\Money::rub($discounted->old_price) }}</s>
                    @endif
                    <span class="product-card__sizes">{{ $variants->pluck('label')->implode(' · ') }}</span>
                @else
                    <span class="muted">{{ __('Not available') }}</span>
                @endif
            </span>
        </span>
    </a>
</article>
