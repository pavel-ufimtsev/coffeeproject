<?php

namespace App\Services\Catalog;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\FilterValue;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductVariant;
use App\Models\Redirect;
use App\Models\Roaster;
use Database\Seeders\CatalogStructureSeeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Restores the catalog from database/data/catalog: products.json (content,
 * variants, taxonomy), images/ and redirects.json. Built once from the old
 * site's web archive; running it again updates products by slug.
 */
class CatalogImporter
{
    /** @var list<string> */
    private array $log = [];

    public function __construct(private readonly ImageProcessor $images) {}

    /**
     * Import everything in the data directory.
     *
     * @return list<string> What was done, one line per step.
     */
    public function import(string $dataPath, bool $withImages = true): array
    {
        $this->log = [];

        (new CatalogStructureSeeder)->run();

        $products = $this->readJson("{$dataPath}/products.json")['products'] ?? [];

        DB::transaction(function () use ($products, $dataPath, $withImages) {
            foreach ($products as $entry) {
                $this->importProduct($entry, $dataPath, $withImages);
            }
        });

        $this->log[] = 'Products: '.count($products).' imported.';

        if (is_file("{$dataPath}/redirects.json")) {
            $this->importRedirects($this->readJson("{$dataPath}/redirects.json"));
        }

        return $this->log;
    }

    /**
     * Create or update one product with its variants, taxonomy and photos.
     *
     * @param  array<string, mixed>  $entry
     */
    private function importProduct(array $entry, string $dataPath, bool $withImages): void
    {
        $root = Category::roots()->where('slug', $entry['root'])->firstOrFail();
        $category = $entry['category'] === $entry['root']
            ? $root
            : Category::where('parent_id', $root->id)->where('slug', $entry['category'])->firstOrFail();

        $product = Product::updateOrCreate(['slug' => $entry['slug']], [
            'category_id' => $category->id,
            'roaster_id' => $entry['roaster'] ? Roaster::where('slug', $entry['roaster'])->value('id') : null,
            'type' => ProductType::from($entry['type']),
            'status' => ProductStatus::from($entry['status']),
            'name' => $entry['name'],
            'tagline' => $entry['tagline'] ?: null,
            'summary' => $entry['summary'] ?: null,
            'description' => $entry['description'] ?: null,
            'specs' => $entry['specs'] ?: null,
            'is_featured' => $entry['featured'],
            'sort' => $entry['sort'],
            'retailcrm_product_id' => $entry['retailcrm_product_id'],
        ]);

        $this->importVariants($product, $entry['variants']);
        $this->importTaxonomy($product, $root, $entry['filters'] ?? [], $entry['collections'] ?? []);

        if ($withImages && $product->images()->doesntExist()) {
            foreach ($entry['images'] as $i => $image) {
                $stored = $this->images->store("{$dataPath}/images/{$image['file']}", $product->slug.($i ? "-{$i}" : ''));
                $product->images()->create([...$stored, 'alt' => $product->name, 'sort' => $i, 'source' => $image['source']]);
            }
        }
    }

    /**
     * Sync the product's variants with the restore data. Prices are only
     * filled in when missing: after the first retailCRM sync they belong to it.
     *
     * @param  list<array<string, mixed>>  $variants
     */
    private function importVariants(Product $product, array $variants): void
    {
        $location = Location::where('retailcrm_store_code', 'V36')->first();
        $keep = [];

        foreach ($variants as $sort => $data) {
            $variant = ProductVariant::firstOrNew(['retailcrm_offer_id' => $data['retailcrm_offer_id']]);

            if ($variant->exists && $variant->product_id !== $product->id) {
                throw new RuntimeException("retailCRM offer {$data['retailcrm_offer_id']} is linked to two products.");
            }

            $variant->fill([
                'product_id' => $product->id,
                'label' => $data['label'],
                'weight_g' => $data['weight_g'],
                'sort' => $sort,
                'is_active' => true,
            ]);
            $variant->price ??= $data['price'];
            $variant->old_price ??= $data['old_price'];
            $variant->save();

            if ($location) {
                $variant->locations()->syncWithoutDetaching([$location->id]);
            }

            $keep[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keep)->delete();
    }

    /**
     * Attach the product to its filter values and collections.
     *
     * @param  array<string, list<string>>  $filters
     * @param  list<string>  $collections
     */
    private function importTaxonomy(Product $product, Category $root, array $filters, array $collections): void
    {
        $valueIds = [];

        foreach ($filters as $group => $slugs) {
            foreach ($slugs as $slug) {
                $value = FilterValue::whereRelation('group', fn ($q) => $q->where('category_id', $root->id)->where('slug', $group))
                    ->where('slug', $slug)
                    ->first();

                if (! $value) {
                    throw new RuntimeException("Unknown filter {$group}/{$slug} for {$product->slug}.");
                }

                $valueIds[] = $value->id;
            }
        }

        $product->syncFilterValues($valueIds);

        $collectionIds = [];
        foreach ($collections as $slug) {
            $collection = ProductCollection::where('category_id', $root->id)->where('slug', $slug)->first()
                ?? throw new RuntimeException("Unknown collection {$slug} for {$product->slug}.");
            $collectionIds[$collection->id] = ['sort' => $product->sort];
        }

        $product->collections()->sync($collectionIds);
    }

    /**
     * Store redirects for old URLs, skipping any that point at themselves.
     *
     * @param  array<string, string>  $redirects
     */
    private function importRedirects(array $redirects): void
    {
        $count = 0;

        foreach ($redirects as $from => $to) {
            $from = Redirect::normalizePath($from);
            $to = Redirect::normalizePath($to);

            if ($from === $to) {
                continue;
            }

            Redirect::updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301]);
            $count++;
        }

        $this->log[] = "Redirects: {$count} imported.";
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Missing catalog data file {$path}.");
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
