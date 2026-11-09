<?php

namespace App\Http\Controllers\Api\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Blog sections (categories) and internal tags.
 */
class TaxonomyController extends BlogApiController
{
    public function categories(): JsonResponse
    {
        $categories = BlogCategory::withCount([
            'posts',
            'posts as visible_posts_count' => fn (Builder $q) => $q->whereIn('blog_posts.id', BlogPost::visible()->select('blog_posts.id')),
        ])->orderBy('sort')->orderBy('name')->get();

        return response()->json(['data' => $categories->map(fn (BlogCategory $c) => $this->category($c))]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $category = BlogCategory::create($request->validate($this->categoryRules()));
        Log::info("blog: {$this->actor($request)->name} created section {$category->slug}");

        return response()->json(['data' => $this->category($category)], 201);
    }

    public function updateCategory(Request $request, BlogCategory $category): JsonResponse
    {
        $old = $category->path();
        $category->update($request->validate($this->categoryRules($category)));

        if ($old !== $category->path()) {
            Redirect::moved($old, $category->path());
        }
        Log::info("blog: {$this->actor($request)->name} updated section {$category->slug}");

        return response()->json(['data' => $this->category($category)]);
    }

    public function destroyCategory(Request $request, BlogCategory $category): JsonResponse
    {
        if ($category->posts()->withTrashed()->exists()) {
            return response()->json(['message' => 'Move the posts out of this section first, or deactivate it.'], 409);
        }

        $category->delete();
        Log::info("blog: {$this->actor($request)->name} deleted section {$category->slug}");

        return response()->json(['deleted' => true]);
    }

    public function tags(Request $request): JsonResponse
    {
        $tags = BlogTag::withCount('posts')
            ->when($request->string('search')->toString(), fn (Builder $q, string $s) => $q->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%"))
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $tags->map(fn (BlogTag $t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->name, 'posts' => $t->posts_count])]);
    }

    public function storeTag(Request $request): JsonResponse
    {
        $tag = BlogTag::create($request->validate([
            'slug' => ['required', 'string', 'max:250', 'unique:blog_tags,slug'],
            'name' => ['required', 'string', 'max:250'],
        ]));

        return response()->json(['data' => ['id' => $tag->id, 'slug' => $tag->slug, 'name' => $tag->name]], 201);
    }

    public function updateTag(Request $request, BlogTag $tag): JsonResponse
    {
        $tag->update($request->validate([
            'slug' => ['sometimes', 'string', 'max:250', Rule::unique('blog_tags', 'slug')->ignore($tag->id)],
            'name' => ['sometimes', 'string', 'max:250'],
        ]));

        return response()->json(['data' => ['id' => $tag->id, 'slug' => $tag->slug, 'name' => $tag->name]]);
    }

    public function destroyTag(Request $request, BlogTag $tag): JsonResponse
    {
        $tag->posts()->detach();
        $tag->delete();
        Log::info("blog: {$this->actor($request)->name} deleted tag {$tag->slug}");

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function category(BlogCategory $c): array
    {
        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'url' => url($c->path()),
            'name' => $c->name,
            'description' => $c->description,
            'seo_title' => $c->seo_title,
            'seo_description' => $c->seo_description,
            'sort' => $c->sort,
            'is_active' => $c->is_active,
            'posts' => $c->posts_count ?? null,
            'visible_posts' => $c->visible_posts_count ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryRules(?BlogCategory $category = null): array
    {
        $required = $category ? 'sometimes' : 'required';

        return [
            'slug' => [$required, 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', Rule::unique('blog_categories', 'slug')->ignore($category?->id)],
            'name' => [$required, 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:250'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
