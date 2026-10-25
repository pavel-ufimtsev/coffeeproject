<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

/**
 * A product page in the catalog. Its content lives here; what can be bought
 * (variants and prices) mirrors retailCRM.
 *
 * @property int $id
 * @property int $category_id
 * @property int|null $roaster_id
 * @property ProductType $type
 * @property ProductStatus $status
 * @property string $slug
 * @property string $name
 * @property string|null $tagline
 * @property string|null $summary
 * @property string|null $description
 * @property array<string, string>|null $specs
 * @property bool $is_featured
 * @property int $sort
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int|null $retailcrm_product_id
 * @property string|null $retailcrm_name
 * @property bool|null $retailcrm_active
 */
#[Fillable([
    'category_id', 'roaster_id', 'type', 'status', 'slug', 'name', 'tagline', 'summary', 'description', 'specs',
    'is_featured', 'sort', 'seo_title', 'seo_description', 'retailcrm_product_id', 'retailcrm_name', 'retailcrm_active',
])]
class Product extends Model
{
    /**
     * Refresh the site navigation when what is on sale changes.
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
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'specs' => 'array',
            'is_featured' => 'boolean',
            'retailcrm_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Roaster, $this>
     */
    public function roaster(): BelongsTo
    {
        return $this->belongsTo(Roaster::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort');
    }

    /**
     * The variants shown on the site.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    /**
     * @return HasOne<ProductImage, $this>
     */
    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort');
    }

    /**
     * @return BelongsToMany<FilterValue, $this>
     */
    public function filterValues(): BelongsToMany
    {
        return $this->belongsToMany(FilterValue::class);
    }

    /**
     * @return BelongsToMany<ProductCollection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ProductCollection::class, 'collection_product', 'product_id', 'collection_id')->withPivot('sort');
    }

    /**
     * Set the product's filter values. A value with a parent (Kenya under
     * Africa) also tags the product with the parent, so the parent's page
     * lists it too.
     *
     * @param  list<int>  $ids
     */
    public function syncFilterValues(array $ids): void
    {
        $all = $ids;

        while ($ids !== []) {
            $ids = array_map('intval', FilterValue::whereIn('id', $ids)->whereNotNull('parent_id')->whereNotIn('parent_id', $all)->pluck('parent_id')->all());
            $all = [...$all, ...$ids];
        }

        $this->filterValues()->sync(array_values(array_unique($all)));
    }

    /**
     * Limit the query to products listed in the catalog.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ProductStatus::Active);
    }

    /**
     * Limit the query to products whose page can be opened (active or archived).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereIn('status', [ProductStatus::Active, ProductStatus::Archived]);
    }

    /**
     * Limit the query to products on sale that something is wrong with: no
     * photo, not linked to retailCRM, switched off there, nothing with a
     * price, or a variant the sync had a problem with. Matches problems().
     *
     * @param  Builder<Product>  $query
     */
    public function scopeNeedsAttention(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)->where(fn (Builder $query) => $query
            ->whereDoesntHave('images')
            ->orWhereNull('retailcrm_product_id')
            ->orWhere('retailcrm_active', false)
            ->orWhereDoesntHave('variants', fn (Builder $q) => $q->where('is_active', true)->where('price', '>', 0))
            ->orWhereHas('variants', fn (Builder $q) => $q->where('is_active', true)->whereNotNull('sync_status')->where('sync_status', '!=', ProductVariant::SYNC_OK)));
    }

    /**
     * Describe what stops the product from selling properly. Expects images
     * and variants to be loaded.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $active = $this->variants->where('is_active', true);

        $problems = [
            'No photo' => $this->images->isEmpty(),
            'Not linked to retailCRM' => $this->retailcrm_product_id === null,
            'Switched off in retailCRM' => $this->retailcrm_active === false,
            'Nothing to buy: no variant with a price' => ! $active->contains(fn (ProductVariant $v) => (float) $v->price > 0),
            'retailCRM problem with a variant' => $active->contains(fn (ProductVariant $v) => $v->sync_status !== null && $v->sync_status !== ProductVariant::SYNC_OK),
        ];

        return array_map(fn (string $problem) => (string) __($problem), array_keys(array_filter($problems)));
    }

    /**
     * Get the storefront path of the product page.
     */
    public function path(): string
    {
        return '/'.$this->category->root()->slug.'/'.$this->slug;
    }

    /**
     * Determine whether the product can currently be bought.
     */
    public function isPurchasable(): bool
    {
        return $this->status === ProductStatus::Active
            && $this->activeVariants->contains(fn (ProductVariant $variant) => $variant->price !== null && (float) $variant->price > 0);
    }

    /**
     * Get the lowest price among the variants shown on the site.
     */
    public function minPrice(): ?float
    {
        $prices = $this->activeVariants->pluck('price')->filter(fn ($price) => $price !== null && (float) $price > 0);

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    /**
     * Get the filled-in specs in display order, with their labels.
     *
     * @return array<string, array{label: string, value: string}>
     */
    public function specList(): array
    {
        $specs = $this->specs ?? [];
        $list = [];

        foreach ($this->type->specFields() as $key => $label) {
            if (filled($specs[$key] ?? null)) {
                $list[$key] = ['label' => $label, 'value' => $specs[$key]];
            }
        }

        return $list;
    }
}
