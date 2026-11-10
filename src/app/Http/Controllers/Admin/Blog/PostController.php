<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Enums\BlogPostStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\User;
use App\Services\Blog\BlogActor;
use App\Services\Blog\BlogMarkdown;
use App\Services\Blog\BlogPostService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Blog posts in the admin. Every change goes through BlogPostService, the
 * same as the agent API, so revisions and redirects work the same way.
 */
class PostController extends Controller
{
    /** An internal address for redirects: starts with a slash, no host. */
    private const PATH_RULE = 'regex:#^/(?!/)[^\s]*$#';

    public function __construct(private BlogPostService $posts) {}

    /**
     * List posts with filters. "Needs a human" lists drafts the agent looked
     * at but left for a person.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(BlogPostStatus::class)],
            'category' => ['nullable', 'string', 'max:120'],
            'triage' => ['nullable', Rule::in([...BlogPost::TRIAGE_DECISIONS, 'none'])],
            'source' => ['nullable', Rule::in(BlogPost::SOURCES)],
            'attention' => ['nullable', 'boolean'],
            'trashed' => ['nullable', 'boolean'],
        ]);

        $posts = BlogPost::query()
            ->with('category')
            ->when($filters['trashed'] ?? false, fn (Builder $q) => $q->onlyTrashed())
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $w) => $w
                ->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $q, string $slug) => $slug === 'none'
                ? $q->whereNull('blog_category_id')
                : $q->whereRelation('category', 'slug', $slug))
            ->when($filters['triage'] ?? null, fn (Builder $q, string $triage) => $triage === 'none'
                ? $q->whereNull('triaged_at')
                : $q->where('triage_decision', $triage))
            ->when($filters['source'] ?? null, fn (Builder $q, string $source) => $q->where('source', $source))
            ->when($filters['attention'] ?? false, fn (Builder $q) => $this->needsHuman($q))
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'published' THEN 2 ELSE 3 END")
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (BlogPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status->value,
                'status_label' => $post->status->label(),
                'is_visible' => $post->isVisible(),
                'category' => $post->category?->name,
                'published_at' => $post->published_at?->toDateString(),
                'source' => $post->source,
                'images_missing' => (int) ($post->import_meta['images_missing'] ?? 0),
                'triage' => $post->triaged_at ? [
                    'decision' => $post->triage_decision,
                    'confidence' => $post->triage_confidence,
                    'reason' => $post->triage_reason,
                ] : null,
                'deleted' => $post->trashed(),
            ]);

        return Inertia::render('admin/blog/posts/Index', [
            'posts' => $posts,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'category' => $filters['category'] ?? '',
                'triage' => $filters['triage'] ?? '',
                'source' => $filters['source'] ?? '',
                'attention' => (bool) ($filters['attention'] ?? false),
                'trashed' => (bool) ($filters['trashed'] ?? false),
            ],
            'statuses' => $this->statusOptions(),
            'categories' => $this->categoryOptions(),
            'counts' => [
                'attention' => $this->needsHuman(BlogPost::query())->count(),
                'trashed' => BlogPost::onlyTrashed()->count(),
                ...BlogPost::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
                    ->mapWithKeys(fn ($total, $status) => [(string) $status => (int) $total])->all(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/blog/posts/Edit', [
            'post' => null,
            'revisions' => [],
            ...$this->formOptions(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $post = $this->attempt(fn () => $this->posts->create(Arr::except($data, 'note'), $this->actor($request), $data['note'] ?? null));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved.')]);

        return to_route('admin.blog.posts.edit', $post);
    }

    public function edit(BlogPost $post): Response
    {
        $post->load(['category', 'cover', 'tags', 'products']);
        $import = $post->import_meta ?? [];

        return Inertia::render('admin/blog/posts/Edit', [
            'post' => [
                ...$post->only(['id', 'slug', 'title', 'excerpt', 'body', 'seo_title', 'seo_description', 'canonical_url', 'noindex', 'author_name', 'source', 'legacy_url', 'reading_minutes']),
                'category' => $post->category?->slug,
                'cover' => $post->cover ? ['id' => $post->cover->id, 'thumb' => $post->cover->thumbUrl(), 'alt' => $post->cover->alt] : null,
                'status' => $post->status->value,
                'status_label' => $post->status->label(),
                'is_visible' => $post->isVisible(),
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'edited_at' => $post->edited_at?->toDateTimeString(),
                'tags' => $post->tags->pluck('name'),
                'products' => $post->products->pluck('slug'),
                'url' => url($post->path()),
                'redirects_to' => $post->isVisible() ? null : Redirect::where('from_path', $post->path())->value('to_path'),
                'words' => $post->wordCount(),
                'missing_images' => array_values((array) ($import['missing_images'] ?? [])),
                'triage' => $post->triaged_at ? [
                    'decision' => $post->triage_decision,
                    'confidence' => $post->triage_confidence,
                    'reason' => $post->triage_reason,
                    'merge_into' => $post->triage_merge_into,
                    'triaged_at' => $post->triaged_at->toDateTimeString(),
                ] : null,
            ],
            'revisions' => $post->revisions()->limit(100)->get()->map(fn (BlogPostRevision $revision) => [
                'id' => $revision->id,
                'author' => $revision->author,
                'note' => $revision->note,
                'title' => $revision->title,
                'created_at' => $revision->created_at?->toDateTimeString(),
            ]),
            ...$this->formOptions($post),
        ]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $request->validate($this->rules($post));

        $this->attempt(fn () => $this->posts->update($post, Arr::except($data, 'note'), $this->actor($request), $data['note'] ?? null));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post saved.')]);

        return back();
    }

    /**
     * Publish, unpublish or retire a post.
     */
    public function status(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $request->validate($this->statusRules());

        $this->changeStatus($post, $data, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($data['action']) {
            'publish' => $post->status === BlogPostStatus::Scheduled ? __('Post scheduled.') : __('Post published.'),
            'unpublish' => __('Post moved back to drafts.'),
            default => __('Post retired.'),
        }]);

        return back();
    }

    /**
     * Publish, unpublish or retire several posts at once.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->statusRules(),
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $actor = $this->actor($request);
        $done = 0;
        $failed = [];

        foreach (BlogPost::whereIn('id', $data['ids'])->get() as $post) {
            try {
                $this->changeStatus($post, $data, $actor);
                $done++;
            } catch (ValidationException) {
                $failed[] = $post->slug;
            }
        }

        Inertia::flash('toast', $failed === []
            ? ['type' => 'success', 'message' => __(':count posts updated.', ['count' => $done])]
            : ['type' => 'warning', 'message' => __(':count posts updated. Not changed: :slugs', ['count' => $done, 'slugs' => implode(', ', $failed)])]);

        return back();
    }

    /**
     * Move a post to the bin, optionally redirecting its address.
     */
    public function destroy(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $request->validate(['redirect_to' => ['nullable', 'string', 'max:255', self::PATH_RULE]]);

        $this->attempt(fn () => $this->posts->delete($post, $this->actor($request), $data['redirect_to'] ?? null), 'redirect_to');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post moved to the bin.')]);

        return to_route('admin.blog.posts.index');
    }

    /**
     * Take a post out of the bin as a draft.
     */
    public function restore(Request $request, int $post): RedirectResponse
    {
        $model = BlogPost::onlyTrashed()->findOrFail($post);
        $model->restore();
        $this->posts->unpublish($model, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post restored as a draft.')]);

        return to_route('admin.blog.posts.edit', $model);
    }

    /**
     * Render Markdown the way the article page does, for the editor's preview.
     */
    public function preview(Request $request, BlogMarkdown $markdown): JsonResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:200000']]);
        $html = $markdown->render((string) ($data['body'] ?? ''))['html'];

        $names = Product::whereIn('slug', preg_match_all('/<!--blog-product:([A-Za-z0-9_-]+)-->/', $html, $m) ? $m[1] : [])->pluck('name', 'slug');
        $html = preg_replace_callback('/<!--blog-product:([A-Za-z0-9_-]+)-->/', fn (array $m) => '<div class="blog-preview-product">'
            .e(isset($names[$m[1]]) ? __('Product card: :name', ['name' => $names[$m[1]]]) : __('Unknown product: :slug', ['slug' => $m[1]]))
            .'</div>', $html) ?? $html;

        return response()->json(['html' => $html]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changeStatus(BlogPost $post, array $data, BlogActor $actor): void
    {
        $this->attempt(fn () => match ($data['action']) {
            'publish' => $this->posts->publish($post, $actor, isset($data['published_at']) ? Date::parse($data['published_at']) : null),
            'unpublish' => $this->posts->unpublish($post, $actor),
            default => $this->posts->archive($post, $actor, $data['redirect_to'] ?? null, $data['note'] ?? null),
        }, 'redirect_to');
    }

    /**
     * @return array<string, mixed>
     */
    private function statusRules(): array
    {
        return [
            'action' => ['required', Rule::in(['publish', 'unpublish', 'archive'])],
            'published_at' => ['nullable', 'date'],
            'redirect_to' => ['nullable', 'string', 'max:255', self::PATH_RULE],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?BlogPost $post = null): array
    {
        return [
            'slug' => [$post ? 'required' : 'nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'title' => ['required', 'string', 'max:250'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:200000'],
            'category' => ['nullable', 'string', 'exists:blog_categories,slug'],
            'cover_image_id' => ['nullable', 'integer', 'exists:blog_images,id'],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:250'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'noindex' => ['boolean'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'tags' => ['array', 'max:30'],
            'tags.*' => ['string', 'max:100'],
            'products' => ['array', 'max:20'],
            'products.*' => ['string', 'exists:products,slug'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Drafts the agent looked at but did not publish or retire.
     *
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    private function needsHuman(Builder $query): Builder
    {
        return $query->where('status', BlogPostStatus::Draft)->whereNotNull('triaged_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?BlogPost $post): array
    {
        $attached = $post?->products->pluck('slug')->all() ?? [];

        return [
            'statuses' => $this->statusOptions(),
            'categories' => $this->categoryOptions(),
            'products' => Product::query()
                ->where(fn (Builder $q) => $q->active()->orWhereIn('slug', $attached))
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (Product $product) => ['value' => $product->slug, 'label' => $product->name]),
            'suggestedRedirect' => $post ? $this->suggestedRedirect($post) : '/blog',
        ];
    }

    /**
     * Where a retired post should lead: the post it was merged into, its
     * section, or the blog.
     */
    private function suggestedRedirect(BlogPost $post): string
    {
        if ($post->triage_merge_into && BlogPost::visible()->where('slug', $post->triage_merge_into)->exists()) {
            return "/blog/{$post->triage_merge_into}";
        }

        return $post->category?->is_active ? $post->category->path() : '/blog';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(fn (BlogPostStatus $status) => ['value' => $status->value, 'label' => $status->label()], BlogPostStatus::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return array_values(BlogCategory::orderBy('sort')->orderBy('name')->get()
            ->map(fn (BlogCategory $c) => ['value' => $c->slug, 'label' => $c->is_active ? $c->name : $c->name.' ('.__('hidden').')'])
            ->all());
    }

    private function actor(Request $request): BlogActor
    {
        /** @var User $user */
        $user = $request->user();

        return BlogActor::user($user);
    }

    /**
     * Run a write and turn rule violations from the service into form errors.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    private function attempt(callable $write, string $field = 'slug'): mixed
    {
        try {
            return $write();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }
}
