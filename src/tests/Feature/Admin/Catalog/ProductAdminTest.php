<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\FilterValue;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductVariant;
use App\Models\Redirect;
use App\Models\User;
use App\Services\RetailCrm\ProductLinker;
use App\Services\RetailCrm\RetailCrm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCatalogFixture();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_customers_cannot_open_the_catalog_admin()
    {
        $this->actingAs(User::factory()->create())->get(route('admin.products.index'))->assertForbidden();
    }

    public function test_products_can_be_listed_and_filtered_by_problems()
    {
        $this->actingAs($this->admin)
            ->get(route('admin.products.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/products/Index')->has('products.data', 3));

        // brazil-fine-cup has no photo; kenya-wolf has one.
        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['attention' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Бразилия Файн Кап')
                ->where('products.data.0.problems', ['Нет фото']));
    }

    public function test_edit_page_shows_the_product()
    {
        $product = $this->product('kenya-wolf');

        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', $product))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/Edit')
                ->where('product.slug', 'kenya-wolf')
                ->has('product.variants', 2)
                ->has('product.images', 1));
    }

    public function test_a_draft_is_created_and_linked_to_retailcrm()
    {
        $this->fakeCrm([
            900 => ['id' => 900, 'name' => 'Руанда Нгома', 'active' => true, 'offers' => [
                ['id' => 9001, 'name' => 'Руанда Нгома (1 кг.)', 'weight' => 1000, 'active' => true, 'price' => 4000, 'prices' => [['priceType' => 'base', 'price' => 4000]]],
                ['id' => 9002, 'name' => 'Руанда Нгома (250 гр.)', 'weight' => 250, 'active' => true, 'price' => 1200, 'prices' => [['priceType' => 'base', 'price' => 1200]]],
                ['id' => 9003, 'name' => 'Руанда Нгома (150 гр.)', 'weight' => 150, 'active' => false, 'price' => 700],
            ]],
        ]);

        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'name' => 'Руанда Нгома',
            'type' => 'coffee',
            'category_id' => Category::where('slug', 'coffee')->value('id'),
            'retailcrm_product_id' => 900,
        ])->assertRedirect();

        $product = Product::where('name', 'Руанда Нгома')->firstOrFail();
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame('ruanda-ngoma', $product->slug);
        $this->assertSame(['250 г', '1 кг'], $product->variants()->pluck('label')->all(), 'Inactive offers are left out; lighter sizes first.');
        $this->assertSame('1200.00', $product->variants()->where('retailcrm_offer_id', 9002)->value('price'), 'Prices are synced right away.');
    }

    public function test_linking_refuses_an_offer_sold_by_another_product()
    {
        $this->fakeCrm([
            142 => ['id' => 142, 'name' => 'Кения Волк', 'active' => true, 'offers' => [['id' => 1001, 'name' => 'Кения Волк 250 гр', 'weight' => 250]]],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.products.retailcrm.store', $this->product('brazil-fine-cup')), ['retailcrm_product_id' => 142, 'offer_ids' => [1001]])
            ->assertSessionHasErrors('retailcrm_product_id');

        $product = $this->product('puer-shu');
        $product->update(['retailcrm_product_id' => null]);
        $this->actingAs($this->admin)
            ->post(route('admin.products.retailcrm.store', $product), ['retailcrm_product_id' => 142, 'offer_ids' => [1001]])
            ->assertSessionHasErrors('retailcrm_product_id', 'Another product is linked to this retailCRM product.');
    }

    public function test_saving_a_product_tags_parents_and_redirects_a_changed_address()
    {
        $product = $this->product('kenya-wolf');
        $ethiopia = FilterValue::where('slug', 'ethiopia')->firstOrFail();
        $sale = ProductCollection::where('slug', 'sale')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'slug' => 'kenya-wolf-aa',
                'filter_value_ids' => [$ethiopia->id],
                'collection_ids' => [$sale->id],
                'specs' => ['country' => 'Кения', 'farm' => '', 'bogus' => 'x'],
            ]))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(['africa', 'ethiopia'], $product->filterValues()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(['country' => 'Кения'], $product->specs);
        $sorts = $sale->products()->orderBy('collection_product.sort')->pluck('products.slug')->all();
        $this->assertSame(['brazil-fine-cup', 'kenya-wolf-aa'], $sorts, 'Added after the existing member.');
        $this->assertSame('/coffee/kenya-wolf-aa', Redirect::where('from_path', '/coffee/kenya-wolf')->value('to_path'));
        $this->get('/coffee/kenya-wolf')->assertRedirect('/coffee/kenya-wolf-aa');
    }

    public function test_a_product_address_cannot_take_a_landing_page_address()
    {
        $product = $this->product('kenya-wolf');

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), $this->payload($product, ['slug' => 'espresso']))
            ->assertSessionHasErrors('slug');
    }

    public function test_a_product_without_a_price_cannot_go_on_sale()
    {
        $product = $this->product('kenya-wolf');
        $product->update(['status' => ProductStatus::Draft]);
        $product->variants()->update(['price' => null]);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), $this->payload($product, ['status' => 'active']))
            ->assertSessionHasErrors('status');
    }

    public function test_photos_can_be_uploaded_reordered_and_deleted()
    {
        $product = $this->product('kenya-wolf');

        $this->actingAs($this->admin)
            ->post(route('admin.products.images.store', $product), ['images' => [UploadedFile::fake()->image('new.jpg', 1600, 1600)]])
            ->assertSessionHasNoErrors();

        $images = $product->images()->get();
        $this->assertCount(2, $images);
        Storage::disk('public')->assertExists($images[1]->path);

        $this->actingAs($this->admin)->put(route('admin.products.images.reorder', $product), ['ids' => [$images[1]->id, $images[0]->id]]);
        $this->assertSame($images[1]->id, $product->mainImage()->value('id'));

        $this->actingAs($this->admin)->delete(route('admin.product-images.destroy', $images[1]));
        Storage::disk('public')->assertMissing($images[1]->path);
        $this->assertSame(1, $product->images()->count());
    }

    public function test_only_drafts_can_be_deleted_and_previewed_by_admins_only()
    {
        $active = $this->product('kenya-wolf');
        $active->update(['status' => ProductStatus::Draft]);
        $this->get('/coffee/kenya-wolf')->assertNotFound();

        $active->update(['status' => ProductStatus::Active]);
        $this->actingAs($this->admin)->delete(route('admin.products.destroy', $active))->assertSessionHasErrors('product');

        $active->update(['status' => ProductStatus::Draft]);
        $this->actingAs($this->admin)->get('/coffee/kenya-wolf')->assertOk()->assertSee('noindex,follow', false);

        $this->actingAs($this->admin)->delete(route('admin.products.destroy', $active))->assertRedirect(route('admin.products.index'));
        $this->assertModelMissing($active);
    }

    public function test_offer_labels_are_shortened()
    {
        $this->assertSame('150 г', ProductLinker::label('Кения Волк', ['name' => 'Кения Волк (150 гр.)', 'weight' => 150]));
        $this->assertSame('1 кг', ProductLinker::label('Кения Волк', ['name' => 'Кения Волк (1 кг.)', 'weight' => 1000]));
        $this->assertSame('M', ProductLinker::label('Футболка Brew Bar', ['name' => 'Футболка Brew Bar (M)', 'weight' => 200]));
        $this->assertSame('Подарочный', ProductLinker::label('Набор', ['name' => 'Подарочный', 'id' => 5]));
    }

    private function product(string $slug): Product
    {
        return Product::where('slug', $slug)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Product $product, array $overrides = []): array
    {
        return array_replace([
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type->value,
            'status' => $product->status->value,
            'category_id' => $product->category_id,
            'roaster_id' => $product->roaster_id,
            'specs' => $product->specs ?? [],
            'is_featured' => $product->is_featured,
            'sort' => $product->sort,
            'filter_value_ids' => $product->filterValues()->pluck('filter_values.id')->all(),
            'collection_ids' => $product->collections()->pluck('collections.id')->all(),
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id, 'label' => $v->label, 'weight_g' => $v->weight_g, 'is_active' => $v->is_active, 'location_ids' => [],
            ])->all(),
        ], $overrides);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function fakeCrm(array $products): void
    {
        $this->mock(RetailCrm::class, function (MockInterface $mock) use ($products) {
            $mock->allows('isConfigured')->andReturnTrue();
            $mock->allows('products')->andReturnUsing(fn (array $ids) => array_intersect_key($products, array_flip($ids)));
        });
    }
}
