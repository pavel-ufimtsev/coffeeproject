<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\Blog\BlogMarkdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The blog: the article list, category pages, articles and the RSS feed,
 * plus the old WordPress addresses that now lead somewhere sensible.
 */
class BlogController extends Controller
{
    private const PER_PAGE = 12;

    private const FEED_SIZE = 30;

    public function index(Request $request): View
    {
        $posts = $this->paginate(BlogPost::visible(), $request);

        return $this->listing($request, $posts, null);
    }

    public function category(Request $request, string $slug): View|RedirectResponse
    {
        $category = BlogCategory::where('slug', $slug)->where('is_active', true)->first();

        // Old WordPress categories that no longer exist go to the blog home.
        if (! $category) {
            return redirect()->route('blog.index', status: 301);
        }

        if ($category->slug !== $slug) {
            return redirect($category->path(), 301);
        }

        $posts = $this->paginate(BlogPost::visible()->whereBelongsTo($category, 'category'), $request);

        return $this->listing($request, $posts, $category);
    }

    public function show(Request $request, BlogMarkdown $markdown, string $slug): View|RedirectResponse
    {
        // Exact match first; other capitalisations of an old address redirect to it.
        $post = BlogPost::where('slug', $slug)->with(['category', 'cover', 'tags'])->first()
            ?? BlogPost::whereRaw('LOWER(slug) = ?', [mb_strtolower($slug)])->with(['category', 'cover', 'tags'])->first();
        $isPreview = $post && ! $post->isVisible() && Gate::allows('access-admin');

        abort_unless($post && ($post->isVisible() || $isPreview), 404);

        if ($post->slug !== $slug) {
            return redirect($post->path(), 301);
        }

        $rendered = $markdown->forPost($post);
        $products = $post->products()->active()->with(['activeVariants', 'mainImage', 'roaster', 'category.parent'])->get();

        $breadcrumbs = [
            ['name' => __('Home'), 'url' => route('home')],
            ['name' => __('Blog'), 'url' => route('blog.index')],
        ];
        if ($post->category) {
            $breadcrumbs[] = ['name' => $post->category->name, 'url' => url($post->category->path())];
        }
        $breadcrumbs[] = ['name' => $post->title, 'url' => url($post->path())];

        $description = $post->seo_description ?: Str::limit(trim((string) $post->excerpt), 160);
        $indexable = $post->isVisible() && ! $post->noindex;

        return view('shop.blog.show', [
            'post' => $post,
            'html' => $rendered['html'],
            // A contents list only helps on longer articles.
            'toc' => count($rendered['toc']) >= 3 ? $rendered['toc'] : [],
            'products' => $products,
            'related' => $this->related($post),
            'isPreview' => $isPreview,
            // The cover is not repeated above the text when the article already shows that photo.
            'showCover' => $post->cover && ! str_contains($rendered['html'], $post->cover->path),
            'breadcrumbs' => $breadcrumbs,
            'meta' => [
                'title' => $post->seo_title ?: "{$post->title} | ".config('shop.name'),
                'description' => $description ?: null,
                'canonical' => $post->canonical_url ?: url($post->path()),
                'robots' => $indexable ? 'index,follow' : 'noindex,follow',
                'image' => $post->cover?->url(),
                'og_type' => 'article',
            ],
            'jsonLd' => [$this->articleJsonLd($post, $description), $this->breadcrumbJsonLd($breadcrumbs)],
        ]);
    }

    /**
     * Old tag pages: there are no tag pages now, so send readers to the
     * category most of the tag's articles are in.
     */
    public function tag(string $slug): RedirectResponse
    {
        $tag = BlogTag::where('slug', $slug)->first();

        $category = $tag ? BlogCategory::where('is_active', true)
            ->withCount(['posts' => fn (Builder $q) => $q->whereIn('blog_posts.id', BlogPost::visible()
                ->whereHas('tags', fn (Builder $t) => $t->whereKey($tag->id))
                ->select('blog_posts.id'))])
            ->orderByDesc('posts_count')
            ->orderBy('sort')
            ->first() : null;

        return redirect($category?->posts_count ? $category->path() : route('blog.index'), 301);
    }

    /**
     * Old WordPress "topics" pages: the category with the same address, or the blog home.
     */
    public function topic(string $slug): RedirectResponse
    {
        $category = BlogCategory::where('slug', $slug)->where('is_active', true)->first();

        return redirect($category ? $category->path() : route('blog.index'), 301);
    }

    /**
     * Old WordPress page numbers: /blog/page/3 is now /blog?page=3.
     */
    public function page(int $page): RedirectResponse
    {
        return redirect($page > 1 ? route('blog.index', ['page' => $page]) : route('blog.index'), 301);
    }

    /**
     * RSS 2.0 feed of the latest articles.
     */
    public function feed(): Response
    {
        $posts = BlogPost::visible()
            ->with(['category', 'cover'])
            ->latest('published_at')
            ->limit(self::FEED_SIZE)
            ->get();

        $body = view('feeds.blog', ['posts' => $posts])->render();

        return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".$body, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }

    /**
     * @param  Builder<BlogPost>  $query
     * @return LengthAwarePaginator<int, BlogPost>
     */
    private function paginate(Builder $query, Request $request): LengthAwarePaginator
    {
        $posts = $query->with(['category', 'cover'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withPath($request->url());

        abort_if($posts->currentPage() > 1 && $posts->isEmpty(), 404);

        return $posts;
    }

    /**
     * @param  LengthAwarePaginator<int, BlogPost>  $posts
     */
    private function listing(Request $request, LengthAwarePaginator $posts, ?BlogCategory $category): View
    {
        $page = $posts->currentPage();
        $title = $category ? ($category->seo_title ?: $category->name) : __('Coffee blog');
        $canonical = $page > 1 ? $request->url().'?page='.$page : $request->url();

        $breadcrumbs = [
            ['name' => __('Home'), 'url' => route('home')],
            ['name' => __('Blog'), 'url' => route('blog.index')],
        ];
        if ($category) {
            $breadcrumbs[] = ['name' => $category->name, 'url' => url($category->path())];
        }

        $categories = BlogCategory::where('is_active', true)
            ->whereHas('posts', fn (Builder $q) => $q->visible())
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return view('shop.blog.index', [
            'posts' => $posts,
            'category' => $category,
            'categories' => $categories,
            'breadcrumbs' => $breadcrumbs,
            'meta' => [
                'title' => $title.($page > 1 ? ' — '.__('page :page', ['page' => $page]) : '').' | '.config('shop.name'),
                'description' => $category
                    ? ($category->seo_description ?: Str::limit((string) $category->description, 160) ?: null)
                    : __('Brewing guides, recipes and stories about coffee from the Coffee Project team.'),
                'canonical' => $canonical,
                'robots' => 'index,follow',
            ],
            'jsonLd' => [$this->breadcrumbJsonLd($breadcrumbs)],
        ]);
    }

    /**
     * Three more articles to read: same category first, then shared tags, then the latest.
     *
     * @return Collection<int, BlogPost>
     */
    private function related(BlogPost $post): Collection
    {
        $base = fn () => BlogPost::visible()->whereKeyNot($post->id)->with(['category', 'cover'])->latest('published_at');

        $related = $post->blog_category_id
            ? $base()->where('blog_category_id', $post->blog_category_id)->limit(3)->get()
            : new Collection;

        if ($related->count() < 3 && $post->tags->isNotEmpty()) {
            $related = $related->merge($base()
                ->whereKeyNot($related->modelKeys())
                ->whereHas('tags', fn (Builder $q) => $q->whereKey($post->tags->modelKeys()))
                ->limit(3 - $related->count())
                ->get());
        }

        if ($related->count() < 3) {
            $related = $related->merge($base()->whereKeyNot($related->modelKeys())->limit(3 - $related->count())->get());
        }

        return $related->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function articleJsonLd(BlogPost $post, ?string $description): array
    {
        $url = url($post->path());

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            '@id' => $url.'#article',
            'mainEntityOfPage' => $url,
            'url' => $url,
            'headline' => Str::limit($post->title, 110, ''),
            'description' => $description ?: null,
            'image' => $post->cover?->url(),
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => ($post->updatedAfterPublishing() ?? $post->published_at)?->toAtomString(),
            'inLanguage' => 'ru',
            'wordCount' => $post->wordCount(),
            'articleSection' => $post->category?->name,
            'author' => ['@type' => 'Organization', 'name' => $post->author_name ?: config('shop.name'), 'url' => url('/')],
            'publisher' => [
                '@type' => 'Organization',
                '@id' => url('/#organization'),
                'name' => config('shop.name'),
                'logo' => ['@type' => 'ImageObject', 'url' => asset('images/shop/logo.png')],
            ],
        ]);
    }

    /**
     * @param  list<array{name: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    private function breadcrumbJsonLd(array $breadcrumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($breadcrumbs)->values()->map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }
}
