<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FilterValue;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductVariant;
use App\Models\Roaster;
use App\Services\Catalog\CatalogListing;
use App\Services\Checkout\CdekDelivery;
use App\Services\Checkout\CourierRates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /**
     * Show a root category: /coffee, /chaj, /accessory.
     */
    public function root(Request $request, string $root): View|RedirectResponse
    {
        $category = $this->findRoot($root);

        return $this->legacyFilterRedirect($request, $category) ?? $this->listing($request, $category, $category);
    }

    /**
     * Show whatever lives at /{root}/{slug}: a child category, a filter or
     * collection landing page, or a product.
     */
    public function show(Request $request, string $root, string $slug): View|RedirectResponse
    {
        $rootCategory = $this->findRoot($root);

        $child = Category::where('parent_id', $rootCategory->id)->where('slug', $slug)->where('is_active', true)->first();
        if ($child) {
            return $this->listing($request, $rootCategory, $child);
        }

        $filterValue = FilterValue::where('slug', $slug)
            ->where('is_landing', true)
            ->whereRelation('group', 'category_id', $rootCategory->id)
            ->with('group')
            ->first();
        if ($filterValue) {
            return $this->listing($request, $rootCategory, $rootCategory, $filterValue);
        }

        $collection = ProductCollection::where('category_id', $rootCategory->id)->where('slug', $slug)->where('is_landing', true)->first();
        if ($collection) {
            return $this->listing($request, $rootCategory, $rootCategory, $collection);
        }

        // Admins can preview drafts.
        $product = Product::query()
            ->unless($request->user()?->can('access-admin'), fn ($query) => $query->visible())
            ->where('slug', $slug)
            ->with('category.parent')
            ->first();
        abort_if($product === null, 404);

        // A product opened under the wrong root moves to its real address.
        if ($product->category->root()->isNot($rootCategory)) {
            return redirect($product->path(), 301);
        }

        return $this->product($product);
    }

    /**
     * Render a listing page.
     */
    private function listing(Request $request, Category $root, Category $category, FilterValue|ProductCollection|null $landing = null): View
    {
        $listing = (new CatalogListing($root, $category, $landing, $request))->build();

        $heading = $landing?->heading() ?? $category->heading();
        $intro = $landing->intro ?? $category->intro;
        $path = $landing ? "/{$root->slug}/{$landing->slug}" : $category->path();
        $indexable = ! $listing->isFiltered() && $listing->products->count() >= config('catalog.min_indexable_products');

        $breadcrumbs = [['name' => __('Home'), 'url' => route('home')]];
        $breadcrumbs[] = ['name' => $root->name, 'url' => url($root->path())];
        if ($category->isNot($root)) {
            $breadcrumbs[] = ['name' => $category->name, 'url' => url($category->path())];
        }
        if ($landing) {
            $breadcrumbs[] = ['name' => $landing->name, 'url' => url($path)];
        }

        return view('shop.catalog.listing', [
            'listing' => $listing,
            'root' => $root,
            'category' => $category,
            'landing' => $landing,
            'heading' => $heading,
            'intro' => $intro,
            'subcategories' => $root->children()->where('is_active', true)->withCount(['products' => fn ($q) => $q->active()])->get()->where('products_count', '>', 0),
            'quickLinks' => FilterValue::brewLandings($root)->get(),
            'breadcrumbs' => $breadcrumbs,
            'meta' => [
                'title' => ($landing ?? $category)->seo_title ?: __(':heading: buy with delivery across Russia | Coffee Project', ['heading' => $heading]),
                'description' => ($landing ?? $category)->seo_description
                    ?: __(':heading from Coffee Project: :count items. Roasted in Ekaterinburg, delivered across Russia.', ['heading' => $heading, 'count' => $listing->products->count()]),
                'canonical' => url($path),
                'robots' => $indexable ? 'index,follow' : 'noindex,follow',
                'image' => $listing->products->first()?->mainImage?->url(),
            ],
            'jsonLd' => [$this->breadcrumbJsonLd($breadcrumbs)],
        ]);
    }

    /**
     * Render a product page.
     */
    private function product(Product $product): View
    {
        $product->load(['activeVariants.locations', 'images', 'roaster', 'filterValues.group', 'collections']);

        $root = $product->category->root();
        $isArchived = $product->status === ProductStatus::Archived;

        $breadcrumbs = [
            ['name' => __('Home'), 'url' => route('home')],
            ['name' => $root->name, 'url' => url($root->path())],
        ];
        if ($product->category->isNot($root)) {
            $breadcrumbs[] = ['name' => $product->category->name, 'url' => url($product->category->path())];
        }
        $breadcrumbs[] = ['name' => $product->name, 'url' => url($product->path())];

        $alternatives = Product::active()
            ->whereKeyNot($product->id)
            ->whereIn('category_id', ($isArchived ? $product->category : $root)->selfAndDescendantIds())
            ->with(['activeVariants', 'mainImage', 'roaster', 'category.parent'])
            ->orderByDesc('is_featured')
            ->orderBy('sort')
            ->limit(4)
            ->get();

        $minPrice = $product->minPrice();
        $landingTags = $product->filterValues->where('is_landing', true)->sortBy(fn (FilterValue $v) => [$v->group->sort, $v->sort])->values();

        return view('shop.catalog.product', [
            'product' => $product,
            'root' => $root,
            'isArchived' => $isArchived,
            'isDraft' => $product->status === ProductStatus::Draft,
            'landingTags' => $landingTags->reject(fn (FilterValue $v) => $v->group->slug === 'brew')->values(),
            'brewTags' => $landingTags->filter(fn (FilterValue $v) => $v->group->slug === 'brew')->values(),
            'delivery' => $this->deliveryFacts(),
            'shipsFrom' => $product->activeVariants->flatMap->locations->unique('id')->values(),
            'grinds' => $product->type->hasGrind() ? ProductType::grinds() : [],
            'alternatives' => $alternatives,
            'breadcrumbs' => $breadcrumbs,
            'meta' => [
                'title' => $product->seo_title ?: ($minPrice && ! $isArchived
                    ? __(':name, price from :price ₽ | Coffee Project', ['name' => $product->name, 'price' => number_format($minPrice, 0, ',', ' ')])
                    : "{$product->name} | Coffee Project"),
                'description' => $product->seo_description ?: Str::limit(str_replace("\n", ' ', $product->summary ?? $product->tagline ?? $product->name), 160),
                'canonical' => url($product->path()),
                'robots' => $product->status === ProductStatus::Active ? 'index,follow' : 'noindex,follow',
                'image' => $product->images->first()?->url(),
                'og_type' => 'product',
            ],
            'jsonLd' => array_filter([$this->productJsonLd($product), $this->breadcrumbJsonLd($breadcrumbs)]),
        ]);
    }

    /**
     * How the order gets to the shopper, for the lines under the cart button.
     *
     * @return array{courier: bool, courier_free_from: float|null, cdek: bool, cdek_free_from: float|null}
     */
    private function deliveryFacts(): array
    {
        $courier = app(CourierRates::class);
        $cdek = app(CdekDelivery::class);

        return [
            'courier' => $courier->enabled(),
            'courier_free_from' => $courier->enabled() && $courier->price() > 0 ? $courier->freeFrom() : null,
            'cdek' => $cdek->enabled(),
            'cdek_free_from' => $cdek->enabled() ? $cdek->freeFrom() : null,
        ];
    }

    /**
     * Send the old site's filter URLs (/coffee?countries[]=кения, ?roasters[]=ingresso)
     * to their new addresses.
     */
    private function legacyFilterRedirect(Request $request, Category $root): ?RedirectResponse
    {
        $countries = $request->query('countries');
        $roasters = $request->query('roasters');

        if (is_array($countries) && $countries !== []) {
            $value = FilterValue::whereRelation('group', fn ($q) => $q->where('category_id', $root->id)->where('slug', 'origin'))
                ->where('is_landing', true)
                ->get()
                ->first(fn (FilterValue $v) => mb_strtolower($v->name) === mb_strtolower((string) $countries[0]));

            return redirect($value ? "/{$root->slug}/{$value->slug}" : $root->path(), 301);
        }

        if (is_array($roasters) && $roasters !== []) {
            $roaster = Roaster::get()->first(fn (Roaster $r) => in_array(mb_strtolower($roasters[0]), [mb_strtolower($r->name), $r->slug, str_replace('-', '', $r->slug)], true));

            return redirect($root->path().($roaster ? '?roaster='.$roaster->slug : ''), 301);
        }

        return null;
    }

    private function findRoot(string $slug): Category
    {
        return Category::roots()->where('slug', $slug)->where('is_active', true)->firstOrFail();
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

    /**
     * @return array<string, mixed>|null
     */
    private function productJsonLd(Product $product): ?array
    {
        $variants = $product->activeVariants->filter(fn (ProductVariant $v) => $v->price !== null && (float) $v->price > 0);

        if ($variants->isEmpty()) {
            return null;
        }

        $availability = $product->status === ProductStatus::Active ? 'https://schema.org/InStock' : 'https://schema.org/Discontinued';

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->summary ?? $product->tagline,
            'image' => $product->images->map->url()->all(),
            'url' => url($product->path()),
            'brand' => $product->roaster ? ['@type' => 'Brand', 'name' => $product->roaster->name] : null,
            'offers' => $variants->values()->map(fn (ProductVariant $v) => [
                '@type' => 'Offer',
                'name' => "{$product->name}, {$v->label}",
                'sku' => (string) ($v->retailcrm_offer_id ?? $v->id),
                'price' => (float) $v->price,
                'priceCurrency' => 'RUB',
                'availability' => $availability,
                'url' => url($product->path()),
            ])->all(),
        ]);
    }
}
