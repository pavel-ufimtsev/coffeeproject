<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\FilterGroup;
use App\Models\FilterValue;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Roaster;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds a catalog listing page: the products of a category (optionally
 * narrowed to a landing filter or collection), the filters a shopper picked
 * from the query string, and the counts shown next to each filter option.
 *
 * Within one filter group the chosen options are combined with OR; different
 * groups are combined with AND. Catalogs are small, so filtering happens in
 * memory on the category's products.
 */
class CatalogListing
{
    public const SORTS = ['default', 'price-asc', 'price-desc'];

    /** @var Collection<int, Product> */
    public Collection $products;

    /** @var list<array{slug: string, name: string, options: list<array{slug: string, name: string, count: int, selected: bool, url: string}>}> */
    public array $filters = [];

    /** @var array<string, list<string>> */
    public array $selected = [];

    public string $sort = 'default';

    public function __construct(
        public readonly Category $root,
        public readonly Category $category,
        public readonly FilterValue|ProductCollection|null $landing,
        private readonly Request $request,
    ) {}

    /**
     * Load and filter the products for the page.
     */
    public function build(): static
    {
        $all = Product::active()
            ->whereIn('category_id', $this->category->selfAndDescendantIds())
            ->with(['activeVariants', 'mainImage', 'roaster', 'category.parent', 'filterValues.group', 'collections', 'activeVariants.locations'])
            ->orderByDesc('is_featured')
            ->orderBy('sort')
            ->get();

        if ($this->landing instanceof FilterValue) {
            $all = $all->filter(fn (Product $p) => $p->filterValues->contains('id', $this->landing->id))->values();
        } elseif ($this->landing instanceof ProductCollection) {
            $all = $all->filter(fn (Product $p) => $p->collections->contains('id', $this->landing->id))
                ->sortBy(fn (Product $p) => data_get($p->collections->firstWhere('id', $this->landing->id), 'pivot.sort'))
                ->values();
        }

        $dimensions = $this->dimensions($all);
        $this->selected = $this->readSelection($dimensions);
        $this->sort = in_array($this->request->query('sort'), self::SORTS, true) ? $this->request->query('sort') : 'default';

        $this->products = $this->sorted($all->filter(fn (Product $p) => $this->matches($p, $dimensions, $this->selected))->values());
        $this->filters = $this->facets($all, $dimensions);

        return $this;
    }

    /**
     * Determine whether a shopper narrowed the page with query filters or sorting.
     */
    public function isFiltered(): bool
    {
        return $this->selected !== [] || $this->sort !== 'default';
    }

    /**
     * Every way products of this page can be filtered, keyed by query parameter:
     * the category's filter groups, collections, roaster and ships-from city.
     * Options no product on the page has are left out, and so are groups with
     * a single option, which would filter nothing.
     *
     * @param  Collection<int, Product>  $products
     * @return array<string, array{name: string, options: array<string, string>, of: \Closure}>
     */
    private function dimensions(Collection $products): array
    {
        $dimensions = [];

        $groups = FilterGroup::where('category_id', $this->root->id)->where('is_visible', true)->with('values')->orderBy('sort')->get();

        foreach ($groups as $group) {
            $dimensions[$group->slug] = [
                'name' => $group->name,
                'options' => $group->values->pluck('name', 'slug')->all(),
                'of' => fn (Product $p) => $p->filterValues->where('filter_group_id', $group->id)->pluck('slug')->all(),
            ];
        }

        $dimensions['collection'] = [
            'name' => __('Collections'),
            'options' => ProductCollection::where('category_id', $this->root->id)->orderBy('sort')->pluck('name', 'slug')->all(),
            'of' => fn (Product $p) => $p->collections->pluck('slug')->all(),
        ];

        $dimensions['roaster'] = [
            'name' => __('Roaster'),
            'options' => Roaster::where('is_active', true)->orderBy('sort')->pluck('name', 'slug')->all(),
            'of' => fn (Product $p) => $p->roaster ? [$p->roaster->slug] : [],
        ];

        $dimensions['from'] = [
            'name' => __('Ships from'),
            'options' => Location::where('is_active', true)->get()->mapWithKeys(fn (Location $l) => [$this->citySlug($l->city) => $l->city])->all(),
            'of' => fn (Product $p) => $p->activeVariants->flatMap->locations->map(fn (Location $l) => $this->citySlug($l->city))->unique()->values()->all(),
        ];

        foreach ($dimensions as $key => $dimension) {
            $present = $products->flatMap(fn (Product $p) => ($dimension['of'])($p))->unique()->all();
            $dimensions[$key]['options'] = array_intersect_key($dimension['options'], array_flip($present));

            if (count($dimensions[$key]['options']) < 2) {
                unset($dimensions[$key]);
            }
        }

        // A landing page's own dimension is already fixed by the URL.
        if ($this->landing instanceof FilterValue) {
            unset($dimensions[$this->landing->group->slug]);
        } elseif ($this->landing instanceof ProductCollection) {
            unset($dimensions['collection']);
        }

        return $dimensions;
    }

    /**
     * Read the shopper's choices from the query string (?brew=espresso,pourover).
     *
     * @param  array<string, array{name: string, options: array<string, string>, of: \Closure}>  $dimensions
     * @return array<string, list<string>>
     */
    private function readSelection(array $dimensions): array
    {
        $selected = [];

        foreach ($dimensions as $key => $dimension) {
            $raw = $this->request->query($key);

            if (! is_string($raw) || $raw === '') {
                continue;
            }

            $values = array_values(array_intersect(explode(',', $raw), array_keys($dimension['options'])));

            if ($values !== []) {
                $selected[$key] = $values;
            }
        }

        return $selected;
    }

    /**
     * @param  array<string, array{name: string, options: array<string, string>, of: \Closure}>  $dimensions
     * @param  array<string, list<string>>  $selected
     */
    private function matches(Product $product, array $dimensions, array $selected, ?string $except = null): bool
    {
        foreach ($selected as $key => $values) {
            if ($key === $except) {
                continue;
            }

            if (array_intersect(($dimensions[$key]['of'])($product), $values) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the filter panel: each option with the number of products it would
     * show given the other groups' choices, and the link that toggles it.
     *
     * @param  Collection<int, Product>  $all
     * @param  array<string, array{name: string, options: array<string, string>, of: \Closure}>  $dimensions
     * @return list<array{slug: string, name: string, options: list<array{slug: string, name: string, count: int, selected: bool, url: string}>}>
     */
    private function facets(Collection $all, array $dimensions): array
    {
        $facets = [];

        foreach ($dimensions as $key => $dimension) {
            $pool = $all->filter(fn (Product $p) => $this->matches($p, $dimensions, $this->selected, except: $key));
            $options = [];

            foreach ($dimension['options'] as $slug => $name) {
                $count = $pool->filter(fn (Product $p) => in_array($slug, ($dimension['of'])($p), true))->count();
                $isSelected = in_array($slug, $this->selected[$key] ?? [], true);

                if ($count === 0 && ! $isSelected) {
                    continue;
                }

                $options[] = [
                    'slug' => $slug,
                    'name' => $name,
                    'count' => $count,
                    'selected' => $isSelected,
                    'url' => $this->toggleUrl($key, $slug),
                ];
            }

            if ($options !== []) {
                $facets[] = ['slug' => $key, 'name' => $dimension['name'], 'options' => $options];
            }
        }

        return $facets;
    }

    /**
     * Get the URL of this page with one filter option switched on or off.
     */
    public function toggleUrl(string $key, string $slug): string
    {
        $selected = $this->selected;
        $current = $selected[$key] ?? [];

        $selected[$key] = in_array($slug, $current, true)
            ? array_values(array_diff($current, [$slug]))
            : [...$current, $slug];

        return $this->url($selected, $this->sort);
    }

    /**
     * Get the URL of this page with a different sort order.
     */
    public function sortUrl(string $sort): string
    {
        return $this->url($this->selected, $sort);
    }

    /**
     * Get the URL of this page without any query filters.
     */
    public function resetUrl(): string
    {
        return $this->request->url();
    }

    /**
     * The shopper's filters and sort as a query string ("?taste=berry"), one
     * group left out: brew-method links keep the other choices.
     */
    public function queryString(?string $except = null): string
    {
        return $this->buildQuery(array_diff_key($this->selected, [$except => true]), $this->sort);
    }

    /**
     * @param  array<string, list<string>>  $selected
     */
    private function url(array $selected, string $sort): string
    {
        return $this->request->url().$this->buildQuery($selected, $sort);
    }

    /**
     * @param  array<string, list<string>>  $selected
     */
    private function buildQuery(array $selected, string $sort): string
    {
        $query = array_filter(array_map(fn (array $values) => implode(',', $values), $selected));

        if ($sort !== 'default') {
            $query['sort'] = $sort;
        }

        return $query ? '?'.http_build_query($query) : '';
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function sorted(Collection $products): Collection
    {
        return match ($this->sort) {
            'price-asc' => $products->sortBy(fn (Product $p) => $p->minPrice() ?? PHP_INT_MAX)->values(),
            'price-desc' => $products->sortByDesc(fn (Product $p) => $p->minPrice() ?? 0)->values(),
            default => $products,
        };
    }

    private function citySlug(string $city): string
    {
        return str($city)->slug()->toString();
    }
}
