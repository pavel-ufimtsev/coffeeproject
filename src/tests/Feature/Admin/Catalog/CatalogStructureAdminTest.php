<?php

namespace Tests\Feature\Admin\Catalog;

use App\Models\Category;
use App\Models\FilterGroup;
use App\Models\FilterValue;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Redirect;
use App\Models\RetailCrmIgnoredProduct;
use App\Models\User;
use App\Services\RetailCrm\RetailCrm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class CatalogStructureAdminTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCatalogFixture();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_every_admin_page_renders()
    {
        $group = FilterGroup::where('slug', 'origin')->firstOrFail();
        $pages = [
            route('admin.dashboard') => 'admin/Dashboard',
            route('admin.products.create') => 'admin/products/Create',
            route('admin.categories.index') => 'admin/categories/Index',
            route('admin.categories.edit', Category::where('slug', 'pujer')->first()) => 'admin/categories/Form',
            route('admin.filter-groups.index') => 'admin/filters/Index',
            route('admin.filter-groups.edit', $group) => 'admin/filters/GroupForm',
            route('admin.filter-values.edit', FilterValue::where('slug', 'kenya')->first()) => 'admin/filters/ValueForm',
            route('admin.collections.index') => 'admin/collections/Index',
            route('admin.collections.edit', ProductCollection::where('slug', 'sale')->first()) => 'admin/collections/Form',
            route('admin.roasters.index') => 'admin/roasters/Index',
            route('admin.roasters.edit', 1) => 'admin/roasters/Form',
            route('admin.locations.edit', 1) => 'admin/roasters/LocationForm',
            route('admin.redirects.index') => 'admin/redirects/Index',
            route('admin.retailcrm.index') => 'admin/retailcrm/Index',
            route('admin.retailcrm.unlinked') => 'admin/retailcrm/Unlinked',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_a_category_is_created_renamed_with_a_redirect_and_deleted()
    {
        $tea = Category::where('slug', 'chaj')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['parent_id' => $tea->id, 'name' => 'Белый чай', 'slug' => 'belyj-chaj', 'is_active' => true])
            ->assertSessionHasNoErrors();
        $category = Category::where('slug', 'belyj-chaj')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $category), ['name' => 'Белый чай', 'slug' => 'white-tea', 'is_active' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame('/chaj/white-tea', Redirect::where('from_path', '/chaj/belyj-chaj')->value('to_path'));

        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category->refresh()));
        $this->assertModelMissing($category);
        $this->assertSame('/chaj', Redirect::where('from_path', '/chaj/belyj-chaj')->value('to_path'), 'Redirects that led to the deleted page now go to the section.');
    }

    public function test_categories_cannot_take_an_address_in_use_or_be_deleted_with_products()
    {
        $coffee = Category::where('slug', 'coffee')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['parent_id' => $coffee->id, 'name' => 'Кения', 'slug' => 'kenya'])
            ->assertSessionHasErrors('slug');

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', Category::where('slug', 'pujer')->first()))
            ->assertSessionHasErrors('category');
    }

    public function test_switching_off_a_landing_page_redirects_it_to_the_section()
    {
        $kenya = FilterValue::where('slug', 'kenya')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.filter-values.update', $kenya), ['name' => 'Кения', 'slug' => 'kenya', 'is_landing' => false])
            ->assertSessionHasNoErrors();

        // The old /moskva/coffee/kenya redirect now goes straight to /coffee.
        $this->assertSame('/coffee', Redirect::where('from_path', '/coffee/kenya')->value('to_path'));
        $this->assertSame('/coffee', Redirect::where('from_path', '/moskva/coffee/kenya')->value('to_path'));

        $this->actingAs($this->admin)
            ->put(route('admin.filter-values.update', $kenya), ['name' => 'Кения', 'slug' => 'kenya', 'is_landing' => true])
            ->assertSessionHasNoErrors();
        $this->get('/coffee/kenya')->assertOk();
    }

    public function test_filter_values_and_groups_can_be_added_and_reordered()
    {
        $group = FilterGroup::where('slug', 'origin')->firstOrFail();
        $africa = FilterValue::where('slug', 'africa')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.filter-groups.filter-values.store', $group), ['name' => 'Руанда', 'slug' => 'rwanda', 'parent_id' => $africa->id, 'is_landing' => true])
            ->assertSessionHasNoErrors();
        $this->get('/coffee/rwanda')->assertOk();

        $ids = $group->values()->pluck('id')->reverse()->values()->all();
        $this->actingAs($this->admin)->put(route('admin.filter-groups.filter-values.reorder', $group), ['ids' => $ids]);
        $this->assertSame($ids, $group->values()->orderBy('sort')->pluck('id')->all());

        $this->actingAs($this->admin)
            ->post(route('admin.filter-groups.store'), ['category_id' => $group->category_id, 'name' => 'Сортировка', 'slug' => 'sort'])
            ->assertSessionHasErrors('slug');
    }

    public function test_collection_products_keep_the_chosen_order()
    {
        $collection = ProductCollection::where('slug', 'unique')->firstOrFail();
        $kenya = Product::where('slug', 'kenya-wolf')->value('id');
        $brazil = Product::where('slug', 'brazil-fine-cup')->value('id');
        $tea = Product::where('slug', 'puer-shu')->value('id');

        $this->actingAs($this->admin)
            ->put(route('admin.collections.update', $collection), ['name' => $collection->name, 'slug' => 'unique', 'is_landing' => true, 'product_ids' => [$brazil, $tea, $kenya]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$brazil, $kenya], $collection->products()->orderBy('collection_product.sort')->pluck('products.id')->all(), 'A tea cannot join a coffee collection.');
    }

    public function test_redirects_are_normalised_and_chains_refused()
    {
        $this->actingAs($this->admin)
            ->post(route('admin.redirects.store'), ['from_path' => 'https://coffeeproject.ru/old-page/', 'to_path' => '/coffee', 'status_code' => 301])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Redirect::where('from_path', '/old-page')->exists());
        $this->get('/old-page')->assertRedirect('/coffee');

        $this->actingAs($this->admin)
            ->post(route('admin.redirects.store'), ['from_path' => '/another', 'to_path' => '/old-page', 'status_code' => 301])
            ->assertSessionHasErrors('to_path');
    }

    public function test_unlinked_retailcrm_products_can_be_hidden()
    {
        $this->mock(RetailCrm::class, function (MockInterface $mock) {
            $mock->allows('isConfigured')->andReturnTrue();
            $mock->allows('url')->andReturn('https://example.retailcrm.ru');
            $mock->allows('activeProducts')->andReturn([
                142 => ['id' => 142, 'name' => 'Кения Волк', 'offers' => []],
                500 => ['id' => 500, 'name' => 'Новый кофе', 'offers' => [['name' => 'Новый кофе 250 гр', 'price' => 900]]],
                501 => ['id' => 501, 'name' => 'Старая кружка', 'offers' => []],
            ]);
        });

        $this->actingAs($this->admin)->get(route('admin.retailcrm.unlinked'))
            ->assertInertia(fn (Assert $page) => $page->has('products', 2));

        $this->actingAs($this->admin)->post(route('admin.retailcrm.ignored.store'), ['products' => [['id' => 501, 'name' => 'Старая кружка']]]);

        $this->actingAs($this->admin)->get(route('admin.retailcrm.unlinked'))
            ->assertInertia(fn (Assert $page) => $page->has('products', 1)->where('products.0.name', 'Новый кофе')->has('ignored', 1));

        $this->actingAs($this->admin)->delete(route('admin.retailcrm.ignored.destroy', RetailCrmIgnoredProduct::first()));
        $this->assertSame(0, RetailCrmIgnoredProduct::count());
    }
}
