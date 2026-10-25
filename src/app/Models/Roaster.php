<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Who roasted the coffee: Coffee Project itself or a partner roastery.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $city
 * @property string|null $story
 * @property string|null $website
 * @property bool $is_partner
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['slug', 'name', 'city', 'story', 'website', 'is_partner', 'is_active', 'sort'])]
class Roaster extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_partner' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
