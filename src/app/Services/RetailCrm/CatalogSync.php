<?php

namespace App\Services\RetailCrm;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RetailCrmSyncRun;
use Throwable;

/**
 * Copies prices and status of linked products and offers from retailCRM.
 * Reads only; never writes to retailCRM.
 */
class CatalogSync
{
    /** retailCRM price type of the regular price. */
    private const BASE_PRICE = 'base';

    /** retailCRM price type of the price before a sale (shown crossed out). */
    private const OLD_PRICE = 'before-sale';

    public function __construct(private readonly RetailCrm $crm) {}

    /**
     * Run the sync and record what happened.
     */
    public function run(): RetailCrmSyncRun
    {
        $run = RetailCrmSyncRun::create(['started_at' => now()]);
        $changes = [];
        $issues = [];

        try {
            $products = Product::whereNotNull('retailcrm_product_id')->with('variants')->get();
            $remote = $this->crm->products(array_values(array_map('intval', $products->pluck('retailcrm_product_id')->all())));

            foreach ($products as $product) {
                $remoteProduct = $remote[$product->retailcrm_product_id] ?? null;

                $isOnSale = $product->status === ProductStatus::Active;

                if ($remoteProduct === null && $isOnSale) {
                    $issues[] = $this->issue($product, null, 'Товар не найден в retailCRM.');
                } elseif ($remoteProduct !== null && ! ($remoteProduct['active'] ?? false) && $isOnSale) {
                    $issues[] = $this->issue($product, null, 'Товар продаётся на сайте, но выключен в retailCRM.');
                }

                $product->update([
                    'retailcrm_name' => $remoteProduct['name'] ?? $product->retailcrm_name,
                    'retailcrm_active' => $remoteProduct ? (bool) ($remoteProduct['active'] ?? false) : null,
                ]);

                /** @var list<array<string, mixed>> $offerRows */
                $offerRows = $remoteProduct['offers'] ?? [];
                $offers = collect($offerRows)->keyBy('id');

                foreach ($product->variants as $variant) {
                    if ($variant->retailcrm_offer_id === null) {
                        continue;
                    }

                    $change = $this->syncVariant($variant, $offers->get($variant->retailcrm_offer_id));

                    if ($change !== null) {
                        $changes[] = ['product' => $product->slug, 'variant' => $variant->label, ...$change];
                    }

                    if ($variant->sync_status !== ProductVariant::SYNC_OK && $variant->is_active && $isOnSale) {
                        $issues[] = $this->issue($product, $variant, $this->describe($variant->sync_status));
                    }
                }
            }

            $run->fill(['products_checked' => $products->count(), 'variants_changed' => count($changes)]);
        } catch (Throwable $e) {
            $run->error = $e->getMessage();
        }

        $run->fill(['finished_at' => now(), 'changes' => $changes, 'issues' => $issues])->save();

        return $run;
    }

    /**
     * Update one variant from its retailCRM offer.
     *
     * @param  array<string, mixed>|null  $offer
     * @return array{from: array{price: ?string, old_price: ?string}, to: array{price: ?string, old_price: ?string}}|null
     */
    private function syncVariant(ProductVariant $variant, ?array $offer): ?array
    {
        $before = ['price' => $variant->price, 'old_price' => $variant->old_price];

        if ($offer === null) {
            $variant->fill(['sync_status' => ProductVariant::SYNC_MISSING, 'retailcrm_active' => null]);
        } else {
            [$price, $oldPrice] = $this->prices($offer);
            $active = (bool) ($offer['active'] ?? true);

            if ($price !== null) {
                $variant->price = (string) $price;
                $variant->old_price = $oldPrice !== null && $oldPrice > $price ? (string) $oldPrice : null;
            }

            $variant->fill([
                'retailcrm_active' => $active,
                'sync_status' => match (true) {
                    $price === null => ProductVariant::SYNC_NO_PRICE,
                    ! $active => ProductVariant::SYNC_INACTIVE,
                    default => ProductVariant::SYNC_OK,
                },
            ]);
        }

        $variant->synced_at = now();
        $variant->save();

        $after = ['price' => $variant->price, 'old_price' => $variant->old_price];

        return $before == $after ? null : ['from' => $before, 'to' => $after];
    }

    /**
     * Read the regular and before-sale prices of an offer. A price of zero
     * counts as no price.
     *
     * @param  array<string, mixed>  $offer
     * @return array{0: ?float, 1: ?float}
     */
    private function prices(array $offer): array
    {
        /** @var list<array<string, mixed>> $priceRows */
        $priceRows = $offer['prices'] ?? [];
        $prices = collect($priceRows)->keyBy('priceType');

        $price = $prices->get(self::BASE_PRICE)['price'] ?? $offer['price'] ?? null;
        $oldPrice = $prices->get(self::OLD_PRICE)['price'] ?? null;

        return [
            $price !== null && (float) $price > 0 ? (float) $price : null,
            $oldPrice !== null && (float) $oldPrice > 0 ? (float) $oldPrice : null,
        ];
    }

    /**
     * @return array{product: string, variant: ?string, retailcrm_product_id: ?int, retailcrm_offer_id: ?int, problem: string}
     */
    private function issue(Product $product, ?ProductVariant $variant, string $problem): array
    {
        return [
            'product' => $product->slug,
            'variant' => $variant?->label,
            'retailcrm_product_id' => $product->retailcrm_product_id,
            'retailcrm_offer_id' => $variant?->retailcrm_offer_id,
            'problem' => $problem,
        ];
    }

    private function describe(?string $status): string
    {
        return match ($status) {
            ProductVariant::SYNC_MISSING => 'Торговое предложение не найдено в retailCRM.',
            ProductVariant::SYNC_INACTIVE => 'Торговое предложение выключено в retailCRM.',
            ProductVariant::SYNC_NO_PRICE => 'В retailCRM нет цены.',
            default => 'Неизвестная проблема.',
        };
    }
}
