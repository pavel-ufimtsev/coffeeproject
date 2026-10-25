<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RetailCrmSyncRun;
use App\Services\RetailCrm\RetailCrm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class CatalogSyncTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCatalogFixture();
    }

    public function test_it_copies_prices_and_sale_prices_from_retailcrm()
    {
        $this->fakeCrm([
            142 => $this->product(142, 'Кения Волк', true, [
                $this->offer(1001, 1299),
                $this->offer(1002, 4500, beforeSale: 5000),
            ]),
            66 => $this->product(66, 'Бразилия Файн Кап', true, [$this->offer(2001, 690)]),
            148 => $this->product(148, 'Пуэр', false, [$this->offer(3001, 780)]),
        ]);

        $this->artisan('retailcrm:sync-catalog')->assertSuccessful();

        $this->assertSame('1299.00', $this->variant(1001)->price);
        $this->assertSame('4500.00', $this->variant(1002)->price);
        $this->assertSame('5000.00', $this->variant(1002)->old_price);
        $this->assertNull($this->variant(2001)->old_price, 'A sale price that ended is cleared.');
        $this->assertSame(ProductVariant::SYNC_OK, $this->variant(1001)->sync_status);

        $run = RetailCrmSyncRun::latest('id')->first();
        $this->assertSame(3, $run->products_checked);
        $this->assertSame(3, $run->variants_changed);
        $this->assertSame([], $run->issues, 'An archived product switched off in retailCRM is not a problem.');
    }

    public function test_it_reports_missing_offers_and_products_switched_off_in_retailcrm()
    {
        $this->fakeCrm([
            142 => $this->product(142, 'Кения Волк', true, [$this->offer(1001, 1177)]),
            66 => $this->product(66, 'Бразилия Файн Кап', false, [$this->offer(2001, 0)]),
        ]);

        $this->artisan('retailcrm:sync-catalog')
            ->expectsOutputToContain('problems need attention')
            ->assertSuccessful();

        $this->assertSame(ProductVariant::SYNC_MISSING, $this->variant(1002)->sync_status);
        $this->assertSame(ProductVariant::SYNC_NO_PRICE, $this->variant(2001)->sync_status);
        $this->assertSame('812.00', $this->variant(2001)->old_price, 'Without a new price the old values stay.');

        $problems = collect(RetailCrmSyncRun::latest('id')->first()->issues)->map(fn (array $i) => "{$i['product']}: {$i['problem']}")->sort()->values()->all();
        $this->assertSame([
            'brazil-fine-cup: В retailCRM нет цены.',
            'brazil-fine-cup: Товар продаётся на сайте, но выключен в retailCRM.',
            'kenya-wolf: Торговое предложение не найдено в retailCRM.',
        ], $problems, 'The archived tea missing from retailCRM is not reported.');

        $this->assertFalse(Product::where('slug', 'brazil-fine-cup')->value('retailcrm_active'));
    }

    public function test_it_records_a_failed_run()
    {
        $this->mock(RetailCrm::class, function (MockInterface $mock) {
            $mock->allows('isConfigured')->andReturnTrue();
            $mock->allows('products')->andThrow(new \RuntimeException('retailCRM request failed: Wrong "apiKey" value.'));
        });

        $this->artisan('retailcrm:sync-catalog')->expectsOutputToContain('Wrong "apiKey" value.')->assertFailed();

        $this->assertNotNull(RetailCrmSyncRun::latest('id')->first()->error);
    }

    public function test_it_needs_retailcrm_to_be_configured()
    {
        $this->artisan('retailcrm:sync-catalog')->expectsOutputToContain('Set RETAILCRM_URL')->assertFailed();
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function fakeCrm(array $products): void
    {
        $this->mock(RetailCrm::class, function (MockInterface $mock) use ($products) {
            $mock->allows('isConfigured')->andReturnTrue();
            $mock->allows('products')->andReturn($products);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array<string, mixed>
     */
    private function product(int $id, string $name, bool $active, array $offers): array
    {
        return ['id' => $id, 'name' => $name, 'active' => $active, 'offers' => $offers];
    }

    /**
     * @return array<string, mixed>
     */
    private function offer(int $id, float $price, ?float $beforeSale = null): array
    {
        return [
            'id' => $id,
            'active' => true,
            'price' => $price,
            'prices' => array_values(array_filter([
                ['priceType' => 'base', 'price' => $price],
                $beforeSale ? ['priceType' => 'before-sale', 'price' => $beforeSale] : null,
            ])),
        ];
    }

    private function variant(int $offerId): ProductVariant
    {
        return ProductVariant::where('retailcrm_offer_id', $offerId)->firstOrFail();
    }
}
