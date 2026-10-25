<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An editorial group of products with a manual order (unique, decaf, sale…).
 *
 * @property int $id
 * @property int $category_id
 * @property string $slug
 * @property string $name
 * @property bool $is_landing
 * @property string|null $h1
 * @property string|null $intro
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int $sort
 */
#[Fillable(['category_id', 'slug', 'name', 'is_landing', 'h1', 'intro', 'seo_title', 'seo_description', 'sort'])]
class ProductCollection extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'collections';

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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_product', 'collection_id')->withPivot('sort')->orderByPivot('sort');
    }

    /**
     * Get the page heading.
     */
    public function heading(): string
    {
        return $this->h1 ?: $this->name;
    }
}
