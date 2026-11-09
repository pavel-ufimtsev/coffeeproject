<?php

namespace App\Http\Controllers\Api\Blog;

use App\Enums\BlogPostStatus;
use App\Http\Resources\Blog\PostResource;
use App\Models\BlogPost;
use App\Services\Blog\BlogPostService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class PostController extends BlogApiController
{
    public function __construct(private BlogPostService $posts) {}

    /**
     * List posts with filters, newest first, 50 a page (up to 200).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_column(BlogPostStatus::cases(), 'value'))],
            'category' => ['nullable', 'string'],
            'tag' => ['nullable', 'string'],
            'source' => ['nullable', Rule::in(BlogPost::SOURCES)],
            'search' => ['nullable', 'string', 'max:200'],
            'triage' => ['nullable', Rule::in([...BlogPost::TRIAGE_DECISIONS, 'none', 'any'])],
            'updated_since' => ['nullable', 'date'],
            'trashed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['published_at', '-published_at', 'updated_at', '-updated_at', 'slug', 'id'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $sort = $filters['sort'] ?? '-published_at';

        $posts = BlogPost::query()
            ->with(['category', 'tags', 'cover'])
            ->when($request->boolean('trashed'), fn (Builder $q) => $q->onlyTrashed())
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $q, $slug) => $slug === 'none'
                ? $q->whereNull('blog_category_id')
                : $q->whereRelation('category', 'slug', $slug))
            ->when($filters['tag'] ?? null, fn (Builder $q, $tag) => $q->whereHas('tags', fn (Builder $t) => $t->where('slug', $tag)->orWhere('name', $tag)))
            ->when($filters['source'] ?? null, fn (Builder $q, $source) => $q->where('source', $source))
            ->when($filters['search'] ?? null, fn (Builder $q, $search) => $q->where(fn (Builder $w) => $w
                ->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%")))
            ->when($filters['triage'] ?? null, fn (Builder $q, $triage) => match ($triage) {
                'none' => $q->whereNull('triaged_at'),
                'any' => $q->whereNotNull('triaged_at'),
                default => $q->where('triage_decision', $triage),
            })
            ->when($filters['updated_since'] ?? null, fn (Builder $q, $since) => $q->where('updated_at', '>=', $since))
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return PostResource::collection($posts);
    }

    public function show(string $post): PostResource
    {
        return $this->full($this->findPost($post, withTrashed: true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $post = $this->attempt(fn () => $this->posts->create(Arr::except($data, 'note'), $this->actor($request), $data['note'] ?? null));

        return $this->full($post)->response()->setStatusCode(201);
    }

    public function update(Request $request, string $post): PostResource
    {
        $model = $this->findPost($post);
        $data = $request->validate($this->rules($model));

        return $this->full($this->attempt(fn () => $this->posts->update($model, Arr::except($data, 'note'), $this->actor($request), $data['note'] ?? null)));
    }

    public function publish(Request $request, string $post): PostResource
    {
        $data = $request->validate(['published_at' => ['nullable', 'date']]);
        $at = isset($data['published_at']) ? now()->parse($data['published_at']) : null;

        return $this->full($this->posts->publish($this->findPost($post), $this->actor($request), $at));
    }

    public function unpublish(Request $request, string $post): PostResource
    {
        return $this->full($this->posts->unpublish($this->findPost($post), $this->actor($request)));
    }

    /**
     * Retire a post, usually with a redirect to the page that replaces it.
     */
    public function archive(Request $request, string $post): PostResource
    {
        $data = $request->validate([
            'redirect_to' => ['nullable', 'string', 'max:255', self::PATH_RULE],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $model = $this->findPost($post);

        return $this->full($this->attempt(fn () => $this->posts->archive($model, $this->actor($request), $data['redirect_to'] ?? null, $data['note'] ?? null), 'redirect_to'));
    }

    public function destroy(Request $request, string $post): JsonResponse
    {
        $data = $request->validate(['redirect_to' => ['nullable', 'string', 'max:255', self::PATH_RULE]]);
        $model = $this->findPost($post);

        $this->attempt(fn () => $this->posts->delete($model, $this->actor($request), $data['redirect_to'] ?? null), 'redirect_to');

        return response()->json(['deleted' => true]);
    }

    private function full(BlogPost $post): PostResource
    {
        $post->loadMissing(['category', 'tags', 'cover', 'images', 'products']);

        return PostResource::make($post)->full();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?BlogPost $post = null): array
    {
        $required = $post ? 'sometimes' : 'required';

        return [
            'slug' => ['sometimes', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'title' => [$required, 'string', 'max:250'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'body' => [$required, 'string', 'max:200000'],
            'category' => ['sometimes', 'nullable', 'string', 'exists:blog_categories,slug'],
            'cover_image_id' => ['sometimes', 'nullable', 'integer', 'exists:blog_images,id'],
            'status' => ['sometimes', Rule::in(array_column(BlogPostStatus::cases(), 'value'))],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'redirect_to' => ['sometimes', 'nullable', 'string', 'max:255', self::PATH_RULE],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:250'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'canonical_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'noindex' => ['sometimes', 'boolean'],
            'author_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tags' => ['sometimes', 'array', 'max:30'],
            'tags.*' => ['string', 'max:100'],
            'products' => ['sometimes', 'array', 'max:20'],
            'products.*' => ['string', 'exists:products,slug'],
            'triage_decision' => ['sometimes', 'nullable', Rule::in(BlogPost::TRIAGE_DECISIONS)],
            'triage_confidence' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'triage_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'triage_merge_into' => ['sometimes', 'nullable', 'string', 'exists:blog_posts,slug'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
