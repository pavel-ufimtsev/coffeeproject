<?php

namespace App\Services\RetailCrm;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Links a site product to a retailCRM product: the chosen offers become the
 * product's variants, then prices are synced right away.
 */
class ProductLinker
{
    public function __construct(
        private readonly RetailCrm $crm,
        private readonly CatalogSync $sync,
    ) {}

    /**
     * Fetch a retailCRM product, or fail validation when it does not exist.
     *
     * @return array<string, mixed>
     */
    public function fetch(int $retailCrmProductId): array
    {
        return $this->crm->products([$retailCrmProductId])[$retailCrmProductId]
            ?? throw ValidationException::withMessages(['retailcrm_product_id' => __('This product was not found in retailCRM.')]);
    }

    /**
     * Link the product and make the chosen offers its variants. Variants
     * already linked to a chosen offer keep their label, order and locations;
     * variants of offers that were not chosen are removed.
     *
     * @param  array<string, mixed>  $remote  the retailCRM product, from fetch()
     * @param  list<int>  $offerIds
     */
    public function link(Product $product, array $remote, array $offerIds): void
    {
        /** @var list<array<string, mixed>> $offerRows */
        $offerRows = $remote['offers'] ?? [];
        $offers = collect($offerRows)->keyBy('id')->only($offerIds);

        if ($offerIds === [] || $offers->count() !== count(array_unique($offerIds))) {
            throw ValidationException::withMessages(['offer_ids' => __('Choose at least one offer of this retailCRM product.')]);
        }

        $taken = ProductVariant::whereIn('retailcrm_offer_id', $offerIds)->where('product_id', '!=', $product->id)->with('product')->first();
        if ($taken) {
            throw ValidationException::withMessages(['offer_ids' => __('Offer :offer is already sold as «:product».', [
                'offer' => $taken->retailcrm_offer_id,
                'product' => $taken->product->name,
            ])]);
        }

        DB::transaction(function () use ($product, $remote, $offers) {
            $product->update([
                'retailcrm_product_id' => (int) $remote['id'],
                'retailcrm_name' => $remote['name'] ?? null,
                'retailcrm_active' => (bool) ($remote['active'] ?? false),
            ]);

            $product->variants()->where(fn ($q) => $q->whereNull('retailcrm_offer_id')->orWhereNotIn('retailcrm_offer_id', $offers->keys()))->delete();

            $existing = $product->variants()->get()->keyBy('retailcrm_offer_id');
            $locationIds = $product->roaster?->locations()->where('is_active', true)->pluck('id')->all() ?? [];
            $sort = (int) $existing->max('sort') + 1;

            foreach ($offers->sortBy(fn (array $offer) => $offer['weight'] ?? PHP_INT_MAX) as $id => $offer) {
                if ($existing->has($id)) {
                    continue;
                }

                $variant = $product->variants()->create([
                    'label' => self::label((string) ($remote['name'] ?? ''), $offer),
                    'weight_g' => isset($offer['weight']) ? (int) $offer['weight'] : null,
                    'retailcrm_offer_id' => (int) $id,
                    'is_active' => true,
                    'sort' => $sort++,
                ]);
                $variant->locations()->sync($locationIds);
            }
        });

        $this->sync->syncProduct($product->refresh());
    }

    /**
     * Make a short variant label from a retailCRM offer: "Кения Волк (150 гр.)"
     * becomes "150 г", "Футболка Brew Bar (M)" becomes "M".
     *
     * @param  array<string, mixed>  $offer
     */
    public static function label(string $productName, array $offer): string
    {
        $name = trim((string) ($offer['name'] ?? ''));
        $rest = Str::startsWith(mb_strtolower($name), mb_strtolower($productName)) ? mb_substr($name, mb_strlen($productName)) : $name;
        // trim() works on bytes and would cut multibyte dashes and letters apart.
        $rest = (string) preg_replace('/^[\s()\[\].,\-–—]+|[\s()\[\].,\-–—]+$/u', '', $rest);
        $weight = isset($offer['weight']) ? (int) $offer['weight'] : 0;

        if ($weight > 0 && ($rest === '' || preg_match('/^\d+([.,]\d+)?\s*(г|гр|кг|g|kg)\.?$/iu', $rest))) {
            return $weight >= 1000 && $weight % 1000 === 0 ? ($weight / 1000).' кг' : "{$weight} г";
        }

        return $rest !== '' ? $rest : ($name !== '' ? $name : (string) ($offer['id'] ?? ''));
    }
}
