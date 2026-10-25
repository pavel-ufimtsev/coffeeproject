<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\CollectionRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Redirect;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * List the collections of each root category.
     */
    public function index(): Response
    {
        return Inertia::render('admin/collections/Index', [
            'roots' => Category::roots()->orderBy('sort')
                ->with(['collections' => fn ($q) => $q->withCount(['products', 'products as active_products_count' => fn ($q) => $q->where('status', ProductStatus::Active)])])
                ->get()
                ->map(fn (Category $root) => [
                    'id' => $root->id,
                    'name' => $root->name,
                    'collections' => $root->collections->map(fn (ProductCollection $collection) => [
                        ...$collection->only(['id', 'name', 'slug', 'is_landing', 'products_count', 'active_products_count']),
                        'url' => $collection->is_landing ? url("/{$root->slug}/{$collection->slug}") : null,
                    ]),
                ]),
        ]);
    }

    /**
     * Show the form for a new collection.
     */
    public function create(Request $request): Response
    {
        return $this->form(null, $request->integer('category') ?: null);
    }

    /**
     * Store a new collection.
     */
    public function store(CollectionRequest $request): RedirectResponse
    {
        $collection = ProductCollection::create([
            ...$request->safe()->except('product_ids'),
            'sort' => (int) ProductCollection::where('category_id', $request->validated('category_id'))->max('sort') + 1,
        ]);
        $this->syncProducts($collection, $request->validated('product_ids', []));

        if ($collection->is_landing) {
            Redirect::release($this->path($collection));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection created.')]);

        return to_route('admin.collections.edit', $collection);
    }

    /**
     * Show the form for editing a collection and its products.
     */
    public function edit(ProductCollection $collection): Response
    {
        return $this->form($collection, $collection->category_id);
    }

    /**
     * Update a collection. A landing page that moves or is switched off
     * redirects from its old address.
     */
    public function update(CollectionRequest $request, ProductCollection $collection): RedirectResponse
    {
        $wasLanding = $collection->is_landing;
        $isLanding = $request->boolean('is_landing');
        $oldPath = $this->path($collection);

        $collection->update($request->safe()->except('product_ids'));
        $this->syncProducts($collection, $request->validated('product_ids', []));

        if ($wasLanding) {
            Redirect::moved($oldPath, $isLanding ? $this->path($collection) : $collection->category->path());
        } elseif ($isLanding) {
            Redirect::release($this->path($collection));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection saved.')]);

        return back();
    }

    /**
     * Save the order of a category's collections.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::apply(ProductCollection::where('category_id', ProductCollection::whereKey($ids[0])->value('category_id')), $ids);

        return back();
    }

    /**
     * Delete a collection. Its landing page redirects to the root category.
     */
    public function destroy(ProductCollection $collection): RedirectResponse
    {
        if ($collection->is_landing) {
            Redirect::moved($this->path($collection), $collection->category->path());
        }

        $collection->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection deleted.')]);

        return to_route('admin.collections.index');
    }

    /**
     * Set the collection's products in the given order. Only products of
     * the collection's root category are kept.
     *
     * @param  list<int>  $ids
     */
    private function syncProducts(ProductCollection $collection, array $ids): void
    {
        $allowed = Product::whereIn('category_id', $collection->category->selfAndDescendantIds())->whereIn('id', $ids)->pluck('id')->all();
        $ids = array_values(array_intersect($ids, $allowed));

        $collection->products()->sync(collect($ids)->mapWithKeys(fn (int $id, int $position) => [$id => ['sort' => $position]])->all());
    }

    private function form(?ProductCollection $collection, ?int $categoryId): Response
    {
        $root = $categoryId ? Category::find($categoryId) : null;
        $present = fn (Product $product) => ['id' => $product->id, 'name' => $product->name, 'status' => $product->status->value, 'status_label' => $product->status->label()];

        return Inertia::render('admin/collections/Form', [
            'collection' => $collection ? [
                ...$collection->only(['id', 'category_id', 'name', 'slug', 'is_landing', 'h1', 'intro', 'seo_title', 'seo_description']),
                'url' => $collection->is_landing ? url($this->path($collection)) : null,
                'products' => $collection->products()->orderBy('collection_product.sort')->get()->map($present),
            ] : null,
            'categoryId' => $root?->id,
            'roots' => Category::roots()->orderBy('sort')->get(['id', 'name', 'slug']),
            'candidates' => $root ? Product::whereIn('category_id', $root->selfAndDescendantIds())->orderBy('name')->get()->map($present) : [],
        ]);
    }

    private function path(ProductCollection $collection): string
    {
        return "/{$collection->category->slug}/{$collection->slug}";
    }
}
