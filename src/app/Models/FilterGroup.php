<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dimension shoppers filter a root category by: brew method, origin, taste…
 *
 * @property int $id
 * @property int $category_id
 * @property string $slug
 * @property string $name
 * @property bool $is_visible
 * @property int $sort
 */
#[Fillable(['category_id', 'slug', 'name', 'is_visible', 'sort'])]
class FilterGroup extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
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
     * @return HasMany<FilterValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(FilterValue::class)->orderBy('sort');
    }
}
