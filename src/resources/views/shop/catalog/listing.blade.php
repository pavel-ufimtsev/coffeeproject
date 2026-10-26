@php
    $sorts = ['default' => __('Recommended'), 'price-asc' => __('Cheaper first'), 'price-desc' => __('More expensive first')];
    // The brew bar on top replaces the brew group in the sidebar.
    $facets = collect($listing->filters)->reject(fn ($filter) => $quickLinks->isNotEmpty() && $filter['slug'] === 'brew');
    $selectedCount = collect($listing->selected)->flatten()->count();
@endphp
<x-layouts.shop :meta="$meta" :json-ld="$jsonLd">
    <div class="container catalog" data-catalog>
        <div class="catalog__head" data-region="head">
            <x-catalog.breadcrumbs :items="$breadcrumbs" />

            <header class="catalog__header">
                <h1>{{ $heading }}</h1>
                @if ($intro)
                    <div class="catalog__intro">{!! nl2br(e($intro)) !!}</div>
                @endif
                @if ($root->slug === 'coffee' && ! $landing && $category->is($root))
                    <a class="quiz-link" href="{{ route('quiz') }}">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01"/></svg>
                        <span>{{ __('Not sure what to choose? Answer five questions and we will pick a coffee for you.') }}</span>
                    </a>
                @endif
            </header>
        </div>

        <div data-region="nav">
            @if ($subcategories->isNotEmpty())
                <nav class="pills" aria-label="{{ __('Categories') }}">
                    <a href="{{ url($root->path()) }}" class="pill" @if ($category->is($root) && ! $landing) aria-current="page" @endif>{{ __('All') }}</a>
                    @foreach ($subcategories as $subcategory)
                        <a href="{{ url($subcategory->path()) }}" class="pill" @if ($category->is($subcategory)) aria-current="page" @endif>{{ $subcategory->name }}</a>
                    @endforeach
                </nav>
            @elseif ($quickLinks->isNotEmpty())
                <nav class="brew-bar" aria-label="{{ __('Brew method') }}">
                    @foreach ($quickLinks as $link)
                        @php($active = $landing?->is($link))
                        <a href="{{ url($active ? $root->path() : "/{$root->slug}/{$link->slug}").$listing->queryString('brew') }}" class="brew-bar__item" @if ($active) aria-current="page" @endif>
                            <span class="brew-bar__icon">
                                @if (file_exists(public_path("images/brew/{$link->slug}.svg")))
                                    <img src="/images/brew/{{ $link->slug }}.svg" alt="" width="28" height="28">
                                @endif
                            </span>
                            <span>{{ $link->name }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>

        <div class="catalog__bar" data-region="bar">
            <div class="catalog__bar-start">
                @if ($facets->isNotEmpty())
                    <button type="button" class="filters-toggle" aria-controls="filters" aria-expanded="false" data-filters-open>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/></svg>
                        {{ __('Filters') }}
                        @if ($selectedCount)
                            <span class="filters-toggle__count">{{ $selectedCount }}</span>
                        @endif
                    </button>
                @endif
                <p class="catalog__count">{{ trans_choice(':count product|:count products', $listing->products->count()) }}</p>
            </div>

            <details class="dropdown" data-dropdown>
                <summary class="dropdown__button">
                    <span class="muted">{{ __('Sort') }}:</span> {{ $sorts[$listing->sort] }}
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <ul class="dropdown__menu">
                    @foreach ($sorts as $sort => $label)
                        <li><a href="{{ $listing->sortUrl($sort) }}" rel="nofollow" @if ($listing->sort === $sort) aria-current="true" @endif>{{ $label }}</a></li>
                    @endforeach
                </ul>
            </details>
        </div>

        <div class="catalog__layout">
            @if ($facets->isNotEmpty())
                <div class="filters-backdrop" data-filters-close></div>
                <aside class="filters" id="filters" aria-label="{{ __('Filters') }}" data-region="filters" data-filters>
                    <div class="filters__header">
                        <p class="filters__title">{{ __('Filters') }}</p>
                        @if ($listing->selected !== [])
                            <a href="{{ $listing->resetUrl() }}{{ $listing->sort !== 'default' ? '?sort='.$listing->sort : '' }}" class="filters__reset">{{ __('Clear') }}</a>
                        @endif
                        <button type="button" class="drawer__close filters__close" data-filters-close>
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                            <span class="visually-hidden">{{ __('Close') }}</span>
                        </button>
                    </div>

                    @foreach ($facets as $filter)
                        @php($chosen = collect($filter['options'])->where('selected', true)->count())
                        <details class="filter" data-group="{{ $filter['slug'] }}" @if ($chosen) open @endif>
                            <summary class="filter__summary">
                                <span>{{ $filter['name'] }}</span>
                                @if ($chosen)
                                    <span class="filter__chosen">{{ $chosen }}</span>
                                @endif
                                <span class="filter__plus" aria-hidden="true"></span>
                            </summary>
                            <ul class="filter__options">
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

                    <button type="button" class="button button--block filters__done" data-filters-close>
                        {{ trans_choice('Show :count product|Show :count products', $listing->products->count()) }}
                    </button>
                </aside>
            @endif

            <section class="catalog__results" aria-label="{{ __('Products') }}" data-results>
                <div class="catalog__spinner" aria-hidden="true"><span class="spinner"></span></div>
                <div data-region="results">
                    @if ($listing->products->isEmpty() && $listing->isFiltered())
                        <div class="catalog__empty">
                            <p>{{ __('Nothing matches these filters.') }}</p>
                            <p><a href="{{ $listing->resetUrl() }}" class="button button--secondary">{{ __('Reset filters') }}</a></p>
                        </div>
                    @elseif ($listing->products->isEmpty())
                        <div class="catalog__empty">
                            <p>{{ __('Nothing here is on sale right now.') }}</p>
                            <p><a href="{{ url('coffee') }}" class="button">{{ __('Choose coffee') }}</a></p>
                        </div>
                    @else
                        <div class="product-grid">
                            @foreach ($listing->products as $product)
                                <x-catalog.product-card :product="$product" />
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-layouts.shop>
