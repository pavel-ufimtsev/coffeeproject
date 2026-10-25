<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order, copied from the cart when the order was placed.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property int|null $retailcrm_offer_id
 * @property string $name
 * @property string|null $variant_label
 * @property string|null $grind
 * @property string|null $grind_label
 * @property string $price
 * @property int $quantity
 * @property string $total
 */
#[Fillable(['product_id', 'product_variant_id', 'retailcrm_offer_id', 'name', 'variant_label', 'grind', 'grind_label', 'price', 'quantity', 'total'])]
class OrderItem extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * "250 г · Для турки"
     */
    public function meta(): string
    {
        return collect([$this->variant_label, $this->grind_label])->filter()->implode(' · ');
    }
}
