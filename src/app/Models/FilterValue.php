<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One option within a filter group (e.g. "espresso", "kenya"). A value marked
 * as landing gets its own indexable page at /{root}/{slug}.
 *
 * @property int $id
 * @property int $filter_group_id
 * @property int|null $parent_id
 * @property string $slug
 * @property string $name
 * @property bool $is_landing
 * @property string|null $h1
 * @property string|null $intro
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int $sort
 */
#[Fillable(['filter_group_id', 'parent_id', 'slug', 'name', 'is_landing', 'h1', 'intro', 'seo_title', 'seo_description', 'sort'])]
class FilterValue extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_landing' => 'boolean',
        ];
    }

    /**
     * Brew-method landing pages of a root category, in menu order: the quick
     * links at the top of /coffee and on the home page.
     *
     * @param  Builder<FilterValue>  $query
     */
    public function scopeBrewLandings(Builder $query, Category $root): void
    {
        $query->where('is_landing', true)
            ->whereRelation('group', fn ($q) => $q->where('category_id', $root->id)->where('slug', 'brew'))
            ->orderBy('sort');
    }

    /**
     * @return BelongsTo<FilterGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(FilterGroup::class, 'filter_group_id');
    }

    /**
     * @return BelongsTo<FilterValue, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FilterValue::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Get the page heading.
     */
    public function heading(): string
    {
        return $this->h1 ?: $this->name;
    }
}
