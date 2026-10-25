<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A size or version of a product sold on the site (250 г, 1 кг, размер M),
 * linked to one retailCRM offer. Prices are copied from retailCRM by the sync.
 *
 * @property int $id
 * @property int $product_id
 * @property string $label
 * @property int|null $weight_g
 * @property int|null $retailcrm_offer_id
 * @property string|null $price
 * @property string|null $old_price
 * @property bool $is_active
 * @property int $sort
 * @property bool|null $retailcrm_active
 * @property string|null $sync_status
 * @property CarbonInterface|null $synced_at
 */
#[Fillable([
    'product_id', 'label', 'weight_g', 'retailcrm_offer_id', 'price', 'old_price', 'is_active', 'sort',
    'retailcrm_active', 'sync_status', 'synced_at',
])]
class ProductVariant extends Model
{
    /** The offer is linked and in good shape. */
    public const SYNC_OK = 'ok';

    /** The linked offer no longer exists in retailCRM. */
    public const SYNC_MISSING = 'missing';

    /** The linked offer is switched off in retailCRM. */
    public const SYNC_INACTIVE = 'inactive';

    /** retailCRM has no usable price for the offer. */
    public const SYNC_NO_PRICE = 'no_price';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'is_active' => 'boolean',
            'retailcrm_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    /**
     * Determine whether the variant shows a crossed-out old price.
     */
    public function isDiscounted(): bool
    {
        return $this->old_price !== null && $this->price !== null && (float) $this->old_price > (float) $this->price;
    }
}
