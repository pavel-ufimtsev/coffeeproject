<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\CategoryRequest;
use App\Models\Category;
use App\Models\Redirect;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Show the category tree.
     */
    public function index(): Response
    {
        $present = fn (Category $category): array => [
            ...$category->only(['id', 'name', 'slug', 'is_active']),
            'url' => url($category->path()),
            'products_count' => (int) $category->getAttribute('products_count'),
            'active_products_count' => (int) $category->getAttribute('active_products_count'),
        ];

        $counts = ['products', 'products as active_products_count' => fn ($q) => $q->where('status', ProductStatus::Active)];

        return Inertia::render('admin/categories/Index', [
            'roots' => Category::roots()->orderBy('sort')
                ->with(['children' => fn ($q) => $q->withCount($counts)])
                ->withCount($counts)
                ->get()
                ->map(fn (Category $root) => [...$present($root), 'children' => $root->children->map($present)]),
        ]);
    }

    /**
     * Show the form for a new category.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/categories/Form', [
            'category' => null,
            'parentId' => $request->integer('parent') ?: null,
            'roots' => Category::roots()->orderBy('sort')->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Store a new category at the end of its siblings.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create([
            ...$request->validated(),
            'sort' => (int) Category::where('parent_id', $request->validated('parent_id'))->max('sort') + 1,
        ]);
        Redirect::release($category->path());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('admin.categories.index');
    }

    /**
     * Show the form for editing a category.
     */
    public function edit(Category $category): Response
    {
        return Inertia::render('admin/categories/Form', [
            'category' => [
                ...$category->only(['id', 'parent_id', 'name', 'slug', 'h1', 'intro', 'seo_title', 'seo_description', 'is_active']),
                'is_root' => $category->isRoot(),
                'url' => url($category->path()),
                'products_count' => $category->products()->count(),
            ],
            'parentId' => $category->parent_id,
            'roots' => Category::roots()->orderBy('sort')->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Update a category. A new address redirects from the old one.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $oldPath = $category->path();
        $category->update($request->validated());
        Redirect::moved($oldPath, $category->path());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return to_route('admin.categories.index');
    }

    /**
     * Save the order of sibling categories.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $parentId = Category::whereKey($ids[0])->value('parent_id');
        SortOrder::apply(Category::where('parent_id', $parentId), $ids);

        return back();
    }

    /**
     * Delete an empty child category; its address redirects to the parent.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->isRoot() || $category->products()->exists() || $category->children()->exists()) {
            throw ValidationException::withMessages(['category' => __('Only empty categories below Coffee, Tea or Accessories can be deleted. Move their products first.')]);
        }

        $path = $category->path();
        $parentPath = $category->parent->path();
        $category->delete();
        Redirect::moved($path, $parentPath);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('admin.categories.index');
    }
}
