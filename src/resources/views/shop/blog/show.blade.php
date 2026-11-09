<x-layouts.shop :meta="$meta" :json-ld="$jsonLd">
    <div class="container blog-post">
        <x-catalog.breadcrumbs :items="$breadcrumbs" />

        @if ($isPreview)
            <p class="notice notice--warning blog-post__preview" role="status">
                {{ __('Preview: this article is not on the site (status: :status).', ['status' => $post->status->label()]) }}
            </p>
        @endif

        <article class="blog-article">
            <header class="blog-article__header">
                @if ($post->category)
                    <a href="{{ url($post->category->path()) }}" class="blog-article__section">{{ $post->category->name }}</a>
                @endif
                <h1>{{ $post->title }}</h1>
                @if ($post->excerpt)
                    <p class="blog-article__lead">{{ $post->excerpt }}</p>
                @endif
                <p class="blog-article__meta">
                    @if ($post->published_at)
                        <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->translatedFormat('j F Y') }}</time>
                    @endif
                    @if ($updated = $post->updatedAfterPublishing())
                        <span>{{ __('Updated :date', ['date' => $updated->translatedFormat('j F Y')]) }}</span>
                    @endif
                    <span>{{ trans_choice(':count min read|:count min read', $post->reading_minutes, ['count' => $post->reading_minutes]) }}</span>
                </p>
            </header>

            @if ($post->cover)
                <figure class="blog-article__cover">
                    <img src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt }}" @if ($post->cover->width) width="{{ $post->cover->width }}" height="{{ $post->cover->height }}" @endif fetchpriority="high">
                    @if ($post->cover->caption || $post->cover->credit)
                        <figcaption>{{ collect([$post->cover->caption, $post->cover->credit ? __('Photo: :credit', ['credit' => $post->cover->credit]) : null])->filter()->join(' · ') }}</figcaption>
                    @endif
                </figure>
            @endif

            @if ($toc)
                <nav class="blog-toc" aria-labelledby="blog-toc-title">
                    <p id="blog-toc-title" class="blog-toc__title">{{ __('In this article') }}</p>
                    <ol>
                        @foreach ($toc as $entry)
                            <li><a href="#{{ $entry['id'] }}">{{ $entry['text'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            <div class="blog-article__body prose">
                {!! $html !!}
            </div>
        </article>

        @if ($products->isNotEmpty())
            <section class="blog-post__section" aria-labelledby="blog-products-title">
                <h2 id="blog-products-title">{{ __('Coffee for this article') }}</h2>
                <div class="product-grid">
                    @foreach ($products as $product)
                        <x-catalog.product-card :product="$product" :heading-level="3" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($related->isNotEmpty())
            <section class="blog-post__section" aria-labelledby="blog-related-title">
                <h2 id="blog-related-title">{{ __('Read next') }}</h2>
                <div class="blog-grid">
                    @foreach ($related as $item)
                        <x-blog.card :post="$item" :heading-level="3" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.shop>
