<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A place orders ship from: a roastery or a partner's warehouse. Maps to a
 * retailCRM warehouse.
 *
 * @property int $id
 * @property int $roaster_id
 * @property string $name
 * @property string $city
 * @property string|null $address
 * @property string|null $retailcrm_store_code
 * @property list<string>|null $roast_days
 * @property bool $is_active
 */
#[Fillable(['roaster_id', 'name', 'city', 'address', 'retailcrm_store_code', 'roast_days', 'is_active'])]
class Location extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roast_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Roaster, $this>
     */
    public function roaster(): BelongsTo
    {
        return $this->belongsTo(Roaster::class);
    }

    /**
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class);
    }
}
