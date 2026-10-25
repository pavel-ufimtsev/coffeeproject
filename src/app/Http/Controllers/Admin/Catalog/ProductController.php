<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\StoreProductRequest;
use App\Http\Requests\Admin\Catalog\UpdateProductRequest;
use App\Models\Category;
use App\Models\FilterGroup;
use App\Models\FilterValue;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Redirect;
use App\Models\Roaster;
use App\Services\RetailCrm\ProductLinker;
use App\Services\RetailCrm\RetailCrmException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * List products, filtered by search term, type, category, status or problems.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'attention' => ['nullable', 'boolean'],
        ]);

        $categoryLabels = collect(Category::options())->pluck('label', 'id');

        $products = Product::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('retailcrm_name', 'like', "%{$search}%")
                ->when(ctype_digit($search), fn (Builder $query) => $query
                    ->orWhere('retailcrm_product_id', $search)
                    ->orWhereRelation('variants', 'retailcrm_offer_id', $search))))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['category'] ?? null, fn (Builder $query, int $id) => $query->whereIn('category_id', Category::find($id)?->selfAndDescendantIds() ?? []))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['attention'] ?? false, fn (Builder $query) => $query->needsAttention())
            ->with(['category.parent', 'images', 'variants'])
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'active' THEN 1 ELSE 2 END")
            ->orderBy('sort')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'tagline' => $product->tagline,
                'thumb' => $product->images->first()?->thumbUrl(),
                'category' => $categoryLabels[$product->category_id] ?? '',
                'type_label' => $product->type->label(),
                'status' => $product->status->value,
                'status_label' => $product->status->label(),
                'min_price' => $product->minPrice(),
                'problems' => $product->status === ProductStatus::Archived ? [] : $product->problems(),
            ]);

        return Inertia::render('admin/products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'category' => (string) ($filters['category'] ?? ''),
                'status' => $filters['status'] ?? '',
                'attention' => (bool) ($filters['attention'] ?? false),
            ],
            'types' => $this->typeOptions(),
            'statuses' => $this->statusOptions(),
            'categories' => Category::options(),
            'attentionCount' => Product::needsAttention()->count(),
        ]);
    }

    /**
     * Show the form for a new product, optionally prefilled from a retailCRM product.
     */
    public function create(Request $request): Response
    {
        $retailCrmProductId = $request->integer('retailcrm_product_id') ?: null;

        return Inertia::render('admin/products/Create', [
            'prefill' => [
                'name' => $request->string('name')->limit(255, '')->value(),
                'retailcrm_product_id' => $retailCrmProductId,
            ],
            'types' => $this->typeOptions(),
            'categories' => Category::options(),
            'roasters' => Roaster::orderBy('sort')->get(['id', 'name']),
        ]);
    }

    /**
     * Create a draft product. When it comes from retailCRM, its active offers
     * become the variants straight away.
     */
    public function store(StoreProductRequest $request, ProductLinker $linker): RedirectResponse
    {
        $remote = null;
        if ($id = $request->validated('retailcrm_product_id')) {
            $remote = $this->fromCrm(fn () => $linker->fetch((int) $id));
        }

        $product = Product::create([
            ...$request->safe()->only(['name', 'type', 'category_id', 'roaster_id']),
            'status' => ProductStatus::Draft,
            'slug' => $this->uniqueSlug($request->validated('name')),
            'sort' => (int) Product::max('sort') + 1,
        ]);

        if ($remote !== null) {
            /** @var list<array<string, mixed>> $offers */
            $offers = $remote['offers'] ?? [];
            $active = collect($offers)->filter(fn (array $offer) => $offer['active'] ?? true)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

            try {
                $linker->link($product, $remote, array_values($active ?: [(int) $offers[0]['id']]));
            } catch (ValidationException|RetailCrmException $e) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => __('Product created, but linking retailCRM failed: :error', [
                    'error' => $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage(),
                ])]);

                return to_route('admin.products.edit', $product);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created. Fill in the details and put it on sale when ready.')]);

        return to_route('admin.products.edit', $product);
    }

    /**
     * Show the form for editing a product.
     */
    public function edit(Product $product): Response
    {
        $product->load(['category.parent', 'images', 'variants.locations', 'filterValues', 'collections']);

        return Inertia::render('admin/products/Edit', [
            'product' => [
                ...$product->only([
                    'id', 'name', 'slug', 'category_id', 'roaster_id', 'tagline', 'summary', 'description', 'is_featured', 'sort',
                    'seo_title', 'seo_description', 'retailcrm_product_id', 'retailcrm_name', 'retailcrm_active',
                ]),
                'type' => $product->type->value,
                'status' => $product->status->value,
                'specs' => (object) ($product->specs ?? []),
                'filter_value_ids' => $product->filterValues->pluck('id'),
                'collection_ids' => $product->collections->pluck('id'),
                'url' => url($product->path()),
                'problems' => $product->problems(),
                'images' => $product->images->map(fn (ProductImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url(),
                    'thumb' => $image->thumbUrl(),
                    'alt' => $image->alt,
                ]),
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    ...$variant->only(['id', 'label', 'weight_g', 'retailcrm_offer_id', 'is_active', 'sync_status']),
                    'price' => $variant->price === null ? null : (float) $variant->price,
                    'old_price' => $variant->old_price === null ? null : (float) $variant->old_price,
                    'synced_at' => $variant->synced_at?->toDateTimeString(),
                    'location_ids' => $variant->locations->pluck('id'),
                ]),
            ],
            'types' => $this->typeOptions(),
            'statuses' => $this->statusOptions(),
            'categories' => Category::options(),
            'roasters' => Roaster::orderBy('sort')->get(['id', 'name']),
            'locations' => Location::with('roaster:id,name')->orderBy('id')->get()->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => "{$location->name} ({$location->city})",
            ]),
            'filterGroups' => FilterGroup::with(['values' => fn ($q) => $q->orderBy('sort')])->orderBy('sort')->get()->map(fn (FilterGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'category_id' => $group->category_id,
                'values' => $group->values->map(fn (FilterValue $value) => $value->only(['id', 'name', 'parent_id'])),
            ]),
            'collections' => ProductCollection::orderBy('sort')->get(['id', 'name', 'category_id']),
            'retailcrmUrl' => config('services.retailcrm.url'),
        ]);
    }

    /**
     * Update a product. When a published page changes address, the old one
     * redirects to the new one.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $wasPublished = $product->status !== ProductStatus::Draft;
        $oldPath = $product->path();

        DB::transaction(function () use ($request, $product) {
            $type = ProductType::from($request->validated('type'));

            $product->fill([
                ...$request->safe()->except(['specs', 'filter_value_ids', 'collection_ids', 'variants']),
                'specs' => array_filter(
                    array_intersect_key($request->validated('specs', []), $type->specFields()),
                    fn ($value) => filled($value),
                ) ?: null,
            ])->save();

            $product->load('category');
            $groupIds = FilterGroup::where('category_id', $product->category->root()->id)->pluck('id');
            $product->syncFilterValues(array_values(array_map('intval', FilterValue::whereIn('id', $request->validated('filter_value_ids', []))
                ->whereIn('filter_group_id', $groupIds)->pluck('id')->all())));

            $this->syncCollections($product, $request->validated('collection_ids', []));

            foreach ($request->validated('variants', []) as $position => $data) {
                $variant = $product->variants()->whereKey($data['id'])->firstOrFail();
                $variant->update([
                    'label' => $data['label'],
                    'weight_g' => $data['weight_g'] ?? null,
                    'is_active' => $data['is_active'] ?? false,
                    'sort' => $position,
                ]);
                $variant->locations()->sync($data['location_ids'] ?? []);
            }
        });

        if ($product->status !== ProductStatus::Draft) {
            $wasPublished ? Redirect::moved($oldPath, $product->path()) : Redirect::release($product->path());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product saved.')]);

        return back();
    }

    /**
     * Delete a draft. Published products are archived instead, so their
     * addresses keep working.
     */
    public function destroy(Product $product): RedirectResponse
    {
        if ($product->status !== ProductStatus::Draft) {
            throw ValidationException::withMessages(['product' => __('Only drafts can be deleted. Archive the product instead.')]);
        }

        $product->images->each(fn (ProductImage $image) => Storage::disk('public')->delete([$image->path, $image->thumb_path]));
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('admin.products.index');
    }

    /**
     * Set the product's collections, adding it to the end of collections it
     * was not in and keeping its place in the others.
     *
     * @param  list<int>  $ids
     */
    private function syncCollections(Product $product, array $ids): void
    {
        $current = $product->collections()->pluck('collection_product.sort', 'collections.id');

        $product->collections()->sync(collect($ids)->mapWithKeys(fn (int $id) => [$id => [
            'sort' => $current[$id] ?? (int) DB::table('collection_product')->where('collection_id', $id)->max('sort') + 1,
        ]])->all());
    }

    /**
     * Make a URL slug from the name that no product uses yet.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;

        for ($i = 2; Product::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /**
     * Run a retailCRM call, turning connection problems into a form error.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function fromCrm(callable $call): mixed
    {
        try {
            return $call();
        } catch (RetailCrmException $e) {
            throw ValidationException::withMessages(['retailcrm_product_id' => $e->getMessage()]);
        }
    }

    /**
     * @return list<array{value: string, label: string, spec_fields: array<string, string>}>
     */
    private function typeOptions(): array
    {
        return array_map(fn (ProductType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'spec_fields' => $type->specFields(),
        ], ProductType::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(fn (ProductStatus $status) => ['value' => $status->value, 'label' => $status->label()], ProductStatus::cases());
    }
}
