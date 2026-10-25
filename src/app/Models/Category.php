<?php

namespace App\Models;

use App\Support\Navigation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * A node in the catalog tree. Root categories (coffee, chaj, accessory) are
 * also the URL prefixes of everything below them.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $slug
 * @property string $name
 * @property string|null $h1
 * @property string|null $intro
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int $sort
 * @property bool $is_active
 */
#[Fillable(['parent_id', 'slug', 'name', 'h1', 'intro', 'seo_title', 'seo_description', 'sort', 'is_active'])]
class Category extends Model
{
    /**
     * Refresh the site navigation when categories change.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(Navigation::CACHE_KEY));
        static::deleted(fn () => Cache::forget(Navigation::CACHE_KEY));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<FilterGroup, $this>
     */
    public function filterGroups(): HasMany
    {
        return $this->hasMany(FilterGroup::class)->orderBy('sort');
    }

    /**
     * @return HasMany<ProductCollection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(ProductCollection::class)->orderBy('sort');
    }

    /**
     * Limit the query to root categories.
     *
     * @param  Builder<Category>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Determine whether this is a root category.
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Get the root category this one belongs to (itself for a root).
     */
    public function root(): Category
    {
        $category = $this;

        while ($category->parent_id !== null) {
            $category = $category->parent;
        }

        return $category;
    }

    /**
     * Get the IDs of this category and every category below it.
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];

        while ($level !== []) {
            $level = array_values(array_map('intval', Category::whereIn('parent_id', $level)->pluck('id')->all()));
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    /**
     * Get the storefront path of the category.
     */
    public function path(): string
    {
        return $this->isRoot() ? "/{$this->slug}" : "/{$this->root()->slug}/{$this->slug}";
    }

    /**
     * Get every category in tree order, for pickers in the admin.
     *
     * @return list<array{id: int, name: string, label: string, root_id: int, depth: int, is_active: bool}>
     */
    public static function options(): array
    {
        $all = Category::orderBy('sort')->orderBy('name')->get()->groupBy(fn (Category $c) => $c->parent_id ?? 0);
        $options = [];

        $walk = function (int $parentId, int $rootId, string $prefix, int $depth) use (&$walk, &$options, $all): void {
            foreach ($all->get($parentId, []) as $category) {
                $root = $depth === 0 ? $category->id : $rootId;
                $label = $prefix.$category->name;
                $options[] = ['id' => $category->id, 'name' => $category->name, 'label' => $label, 'root_id' => $root, 'depth' => $depth, 'is_active' => $category->is_active];
                $walk($category->id, $root, "{$label} › ", $depth + 1);
            }
        };
        $walk(0, 0, '', 0);

        return $options;
    }

    /**
     * Get the page heading.
     */
    public function heading(): string
    {
        return $this->h1 ?: $this->name;
    }
}
