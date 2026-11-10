<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Blog sections at /blog/c/{slug}.
 */
class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/blog/categories/Index', [
            'categories' => BlogCategory::withCount(['posts', 'posts as visible_posts_count' => fn ($q) => $q->visible()])
                ->orderBy('sort')->orderBy('name')->get()
                ->map(fn (BlogCategory $c) => [
                    ...$c->only(['id', 'slug', 'name', 'is_active', 'sort']),
                    'url' => url($c->path()),
                    'posts' => (int) $c->getAttribute('posts_count'),
                    'visible_posts' => (int) $c->getAttribute('visible_posts_count'),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/blog/categories/Form', ['category' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        BlogCategory::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section created.')]);

        return to_route('admin.blog.categories.index');
    }

    public function edit(BlogCategory $category): Response
    {
        return Inertia::render('admin/blog/categories/Form', [
            'category' => [
                ...$category->only(['id', 'slug', 'name', 'description', 'seo_title', 'seo_description', 'sort', 'is_active']),
                'url' => url($category->path()),
                'posts' => $category->posts()->withTrashed()->count(),
            ],
        ]);
    }

    /**
     * Save a section. A new address redirects the old one.
     */
    public function update(Request $request, BlogCategory $category): RedirectResponse
    {
        $old = $category->path();
        $category->update($this->validated($request, $category));

        if ($old !== $category->path()) {
            Redirect::moved($old, $category->path());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section saved.')]);

        return to_route('admin.blog.categories.index');
    }

    /**
     * Delete an empty section.
     */
    public function destroy(BlogCategory $category): RedirectResponse
    {
        if ($category->posts()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['category' => __('Move the posts out of this section first, or hide it.')]);
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section deleted.')]);

        return to_route('admin.blog.categories.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BlogCategory $category = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', Rule::unique('blog_categories', 'slug')->ignore($category?->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'seo_title' => ['nullable', 'string', 'max:250'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'sort' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
        ]);
    }
}
