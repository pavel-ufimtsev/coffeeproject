<x-layouts.shop :meta="$meta" :json-ld="$jsonLd">
    <div class="container blog">
        <x-catalog.breadcrumbs :items="$breadcrumbs" />

        <header class="blog__header">
            <h1>{{ $category?->name ?? __('Coffee blog') }}</h1>
            @if ($category?->description)
                <p class="blog__lead">{{ $category->description }}</p>
            @elseif (! $category)
                <p class="blog__lead">{{ __('Brewing guides, recipes and stories about coffee from the Coffee Project team.') }}</p>
            @endif
        </header>

        @if ($categories->isNotEmpty())
            <nav class="pills" aria-label="{{ __('Blog sections') }}">
                <a href="{{ route('blog.index') }}" class="pill" @if (! $category) aria-current="page" @endif>{{ __('All') }}</a>
                @foreach ($categories as $item)
                    <a href="{{ url($item->path()) }}" class="pill" @if ($category?->is($item)) aria-current="page" @endif>{{ $item->name }}</a>
                @endforeach
            </nav>
        @endif

        @if ($posts->isEmpty())
            <p class="blog__empty">{{ __('No articles here yet.') }}</p>
        @else
            <div class="blog-grid">
                @foreach ($posts as $post)
                    <x-blog.card :post="$post" />
                @endforeach
            </div>
        @endif

        @if ($posts->hasPages())
            <nav class="pager" aria-label="{{ __('Pages') }}">
                @if ($posts->onFirstPage())
                    <span class="pager__link pager__link--off">{{ __('Back') }}</span>
                @else
                    <a class="pager__link" rel="prev" href="{{ $posts->currentPage() === 2 ? $posts->path() : $posts->previousPageUrl() }}">{{ __('Back') }}</a>
                @endif
                <ol class="pager__pages">
                    @foreach (range(1, $posts->lastPage()) as $number)
                        @if ($number === 1 || $number === $posts->lastPage() || abs($number - $posts->currentPage()) <= 2)
                            <li>
                                @if ($number === $posts->currentPage())
                                    <span class="pager__page" aria-current="page">{{ $number }}</span>
                                @else
                                    <a class="pager__page" href="{{ $number === 1 ? $posts->path() : $posts->url($number) }}">{{ $number }}</a>
                                @endif
                            </li>
                        @elseif (abs($number - $posts->currentPage()) === 3)
                            <li class="pager__gap" aria-hidden="true">…</li>
                        @endif
                    @endforeach
                </ol>
                @if ($posts->hasMorePages())
                    <a class="pager__link" rel="next" href="{{ $posts->nextPageUrl() }}">{{ __('Next') }}</a>
                @else
                    <span class="pager__link pager__link--off">{{ __('Next') }}</span>
                @endif
            </nav>
        @endif
    </div>
</x-layouts.shop>
