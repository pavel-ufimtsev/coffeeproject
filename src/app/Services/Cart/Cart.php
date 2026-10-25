<?php

namespace App\Services\Cart;

use App\Enums\ProductStatus;
use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * The shopper's cart, kept in the session until the order is placed.
 *
 * A line is one variant in one grind ("250 г, для турки"), so the same coffee
 * can sit in the cart twice with different grinds. Lines whose variant can no
 * longer be bought are dropped the next time the cart is read.
 */
class Cart
{
    public const SESSION_KEY = 'cart';

    public const MAX_QUANTITY = 20;

    /** @var Collection<string, CartLine>|null */
    private ?Collection $lines = null;

    public function __construct(private readonly Session $session) {}

    /**
     * Add a variant, or more of it if the same line is already in the cart.
     * Returns the line's key.
     */
    public function add(ProductVariant $variant, ?string $grind, int $quantity = 1): string
    {
        $key = CartLine::key($variant->id, $grind);
        $items = $this->items();

        $items[$key] = [
            'variant_id' => $variant->id,
            'grind' => $grind,
            'quantity' => min(self::MAX_QUANTITY, ($items[$key]['quantity'] ?? 0) + $quantity),
        ];

        $this->save($items);

        return $key;
    }

    /**
     * Set a line's quantity; zero removes it.
     */
    public function update(string $key, int $quantity): void
    {
        $items = $this->items();

        if (! isset($items[$key])) {
            return;
        }

        if ($quantity <= 0) {
            unset($items[$key]);
        } else {
            $items[$key]['quantity'] = min(self::MAX_QUANTITY, $quantity);
        }

        $this->save($items);
    }

    public function remove(string $key): void
    {
        $this->update($key, 0);
    }

    /**
     * Empty the cart, once the order is placed.
     */
    public function clear(): void
    {
        $this->save([]);
    }

    /**
     * The lines that can still be bought, in the order they were added.
     *
     * @return Collection<string, CartLine>
     */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $items = $this->items();

        if ($items === []) {
            return $this->lines = collect();
        }

        $variants = ProductVariant::whereIn('id', array_column($items, 'variant_id'))
            ->where('is_active', true)
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->whereRelation('product', 'status', ProductStatus::Active)
            ->with(['product.mainImage', 'product.category.parent', 'product.roaster'])
            ->get()
            ->keyBy('id');

        $lines = collect($items)
            ->filter(fn (array $item) => $variants->has($item['variant_id']))
            ->map(fn (array $item, string $key) => new CartLine($key, $variants[$item['variant_id']], $item['grind'], $item['quantity']));

        if ($lines->count() !== count($items)) {
            $this->save(array_intersect_key($items, $lines->all()));
        }

        return $this->lines = $lines;
    }

    /**
     * The number of packs in the cart, for the header badge.
     */
    public function count(): int
    {
        return $this->items() === [] ? 0 : $this->lines()->sum('quantity');
    }

    public function subtotal(): float
    {
        return $this->lines()->sum(fn (CartLine $line) => $line->total());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return array<string, array{variant_id: int, grind: string|null, quantity: int}>
     */
    private function items(): array
    {
        $items = $this->session->get(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    /**
     * @param  array<string, array{variant_id: int, grind: string|null, quantity: int}>  $items
     */
    private function save(array $items): void
    {
        $this->session->put(self::SESSION_KEY, $items);
        $this->lines = null;
    }
}
