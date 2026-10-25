<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Redirect;
use App\Services\Catalog\CatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    public function test_it_restores_structure_products_variants_and_redirects()
    {
        $this->importCatalogFixture();

        $this->assertSame(['accessory', 'chaj', 'coffee'], Category::roots()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(3, Product::count());
        $this->assertSame(4, ProductVariant::count());
        $this->assertSame(3, Redirect::count());

        $kenya = Product::where('slug', 'kenya-wolf')->first();
        $this->assertSame(ProductStatus::Active, $kenya->status);
        $this->assertSame('coffee-project', $kenya->roaster->slug);
        $this->assertSame([1001, 1002], $kenya->variants->pluck('retailcrm_offer_id')->all());
        $this->assertTrue($kenya->variants->first()->locations()->where('city', 'Екатеринбург')->exists());
        $this->assertSame(['unique'], $kenya->collections->pluck('slug')->all());

        $tea = Product::where('slug', 'puer-shu')->first();
        $this->assertSame(ProductStatus::Archived, $tea->status);
        $this->assertSame('/chaj/puer-shu', $tea->path());
    }

    public function test_a_country_also_tags_its_continent()
    {
        $this->importCatalogFixture();

        $slugs = Product::where('slug', 'kenya-wolf')->first()->filterValues->pluck('slug')->sort()->values()->all();

        $this->assertSame(['africa', 'berry', 'french', 'kenya', 'pourover'], $slugs);
    }

    public function test_photos_are_stored_in_two_sizes()
    {
        $this->importCatalogFixture();

        $image = Product::where('slug', 'kenya-wolf')->first()->images->first();

        Storage::disk('public')->assertExists([$image->path, $image->thumb_path]);
        $this->assertSame('archive', $image->source);
    }

    public function test_running_it_again_keeps_synced_prices_and_does_not_duplicate()
    {
        $this->importCatalogFixture();

        ProductVariant::where('retailcrm_offer_id', 1001)->update(['price' => 1299]);

        app(CatalogImporter::class)->import($this->fixturePath);

        $this->assertSame(3, Product::count());
        $this->assertSame(4, ProductVariant::count());
        $this->assertSame('1299.00', ProductVariant::where('retailcrm_offer_id', 1001)->first()->price);
        $this->assertSame(1, Product::where('slug', 'kenya-wolf')->first()->images()->count());
    }

    public function test_the_command_reports_a_missing_data_directory()
    {
        $this->artisan('catalog:import', ['--path' => '/nonexistent'])
            ->expectsOutputToContain('Missing catalog data file')
            ->assertFailed();
    }
}
