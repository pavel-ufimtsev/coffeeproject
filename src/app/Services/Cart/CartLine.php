<?php

namespace App\Services\Cart;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * One row of the cart: a variant, the grind it is ordered in, and how many.
 */
class CartLine
{
    public function __construct(
        public readonly string $key,
        public readonly ProductVariant $variant,
        public readonly ?string $grind,
        public readonly int $quantity,
    ) {}

    /**
     * The line's key in the cart: variant and grind ("12-cezve", "12-none").
     */
    public static function key(int $variantId, ?string $grind): string
    {
        return $variantId.'-'.($grind ?? 'none');
    }

    public function product(): Product
    {
        return $this->variant->product;
    }

    public function grindLabel(): ?string
    {
        return $this->grind === null ? null : (ProductType::grinds()[$this->grind] ?? null);
    }

    public function price(): float
    {
        return (float) $this->variant->price;
    }

    public function total(): float
    {
        return $this->price() * $this->quantity;
    }
}
