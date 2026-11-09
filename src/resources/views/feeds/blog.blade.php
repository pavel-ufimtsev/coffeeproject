<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ __('Coffee blog') }} | {{ config('shop.name') }}</title>
        <link>{{ route('blog.index') }}</link>
        <description>{{ __('Brewing guides, recipes and stories about coffee from the Coffee Project team.') }}</description>
        <language>ru</language>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml"/>
@if ($posts->isNotEmpty())
        <lastBuildDate>{{ $posts->first()->published_at->toRssString() }}</lastBuildDate>
@endif
@foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ url($post->path()) }}</link>
            <guid isPermaLink="true">{{ url($post->path()) }}</guid>
            <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
@if ($post->category)
            <category>{{ $post->category->name }}</category>
@endif
@if ($post->excerpt)
            <description>{{ $post->excerpt }}</description>
@endif
@if ($post->cover)
            <enclosure url="{{ $post->cover->url() }}" type="image/webp" length="0"/>
@endif
        </item>
@endforeach
    </channel>
</rss>
