<x-layouts.shop :meta="$meta" :json-ld="$jsonLd">
    <div class="container catalog">
        <x-catalog.breadcrumbs :items="$breadcrumbs" />

        <header class="catalog__header">
            <h1>{{ $heading }}</h1>
            @if ($intro)
                <div class="catalog__intro">{!! nl2br(e($intro)) !!}</div>
            @endif
        </header>

        @if ($subcategories->isNotEmpty())
            <nav class="chips" aria-label="{{ __('Categories') }}">
                <a href="{{ url($root->path()) }}" class="chip" @if ($category->is($root) && ! $landing) aria-current="page" @endif>{{ __('All') }}</a>
                @foreach ($subcategories as $subcategory)
                    <a href="{{ url($subcategory->path()) }}" class="chip" @if ($category->is($subcategory)) aria-current="page" @endif>{{ $subcategory->name }}</a>
                @endforeach
            </nav>
        @elseif ($quickLinks->isNotEmpty())
            <nav class="chips" aria-label="{{ __('Brew method') }}">
                @foreach ($quickLinks as $link)
                    <a href="{{ url("/{$root->slug}/{$link->slug}") }}" class="chip" @if ($landing?->is($link)) aria-current="page" @endif>{{ $link->name }}</a>
                @endforeach
            </nav>
        @endif

        <div class="catalog__layout">
            @if ($listing->filters !== [])
                <details class="catalog__filters" data-filters open @if ($listing->isFiltered()) data-filtered @endif>
                    <summary class="catalog__filters-toggle">{{ __('Filters') }}</summary>
                    @foreach ($listing->filters as $filter)
                        <details class="filter" @if ($loop->index < 3 || collect($filter['options'])->contains('selected', true)) open @endif>
                            <summary>{{ $filter['name'] }}</summary>
                            <ul>
                                @foreach ($filter['options'] as $option)
                                    <li>
                                        <a href="{{ $option['url'] }}" rel="nofollow" class="filter__option" @if ($option['selected']) aria-current="true" @endif>
                                            <span class="filter__check" aria-hidden="true"></span>
                                            <span>{{ $option['name'] }}</span>
                                            <span class="filter__count">{{ $option['count'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endforeach

                    @if ($listing->isFiltered())
                        <a href="{{ $listing->resetUrl() }}" class="link-button">{{ __('Reset filters') }}</a>
                    @endif
                </details>
            @endif

            <section class="catalog__results" aria-label="{{ __('Products') }}">
                <div class="catalog__toolbar">
                    <p class="muted">{{ trans_choice(':count product|:count products', $listing->products->count()) }}</p>
                    <nav class="sort" aria-label="{{ __('Sort') }}">
                        @foreach (['default' => __('Recommended'), 'price-asc' => __('Cheaper first'), 'price-desc' => __('More expensive first')] as $sort => $label)
                            <a href="{{ $listing->sortUrl($sort) }}" rel="nofollow" @if ($listing->sort === $sort) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>

                @if ($listing->products->isEmpty() && $listing->isFiltered())
                    <div class="card">
                        <p>{{ __('Nothing matches these filters.') }}</p>
                        <p><a href="{{ $listing->resetUrl() }}">{{ __('Reset filters') }}</a></p>
                    </div>
                @elseif ($listing->products->isEmpty())
                    <div class="card">
                        <p>{{ __('Nothing here is on sale right now.') }}</p>
                        <p><a href="{{ url('coffee') }}">{{ __('Choose coffee') }}</a></p>
                    </div>
                @else
                    <div class="product-grid">
                        @foreach ($listing->products as $product)
                            <x-catalog.product-card :product="$product" />
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.shop>
