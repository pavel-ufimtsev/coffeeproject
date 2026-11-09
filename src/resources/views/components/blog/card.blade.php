@props(['post', 'headingLevel' => 2])
{{-- An article in a list: the whole card opens the article, its title link is stretched over it. --}}
<article class="blog-card">
    <div class="blog-card__media">
        @if ($post->cover)
            <img src="{{ $post->cover->thumbUrl() }}" alt="" width="640" height="400" loading="lazy" class="blog-card__image">
        @else
            <span class="blog-card__placeholder" aria-hidden="true">
                <img src="/images/shop/logo.png" alt="" width="56" height="56">
            </span>
        @endif
    </div>
    <div class="blog-card__body">
        <p class="blog-card__meta">
            @if ($post->category)
                <span>{{ $post->category->name }}</span>
            @endif
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time>
        </p>
        <a href="{{ url($post->path()) }}" class="blog-card__title" role="heading" aria-level="{{ $headingLevel }}">{{ $post->title }}</a>
        @if ($post->excerpt)
            <p class="blog-card__excerpt">{{ \Illuminate\Support\Str::limit($post->excerpt, 160) }}</p>
        @endif
    </div>
</article>
