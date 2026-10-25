<?php

namespace Tests\Feature\Shop;

use App\Models\ProductVariant;
use App\Services\Cart\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class CartTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCatalogFixture();
    }

    private function variant(int $offerId): ProductVariant
    {
        return ProductVariant::where('retailcrm_offer_id', $offerId)->firstOrFail();
    }

    public function test_adding_returns_the_redrawn_drawer()
    {
        $variant = $this->variant(1001);

        $this->postJson(route('cart.store'), ['variant' => $variant->id, 'grind' => 'cezve'])
            ->assertOk()
            ->assertJson(['count' => 1])
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Кения Волк') && str_contains($html, 'Для турки'));

        $this->assertSame(
            [$variant->id.'-cezve' => ['variant_id' => $variant->id, 'grind' => 'cezve', 'quantity' => 1]],
            session(Cart::SESSION_KEY),
        );
    }

    public function test_the_same_line_adds_up_and_another_grind_is_a_new_line()
    {
        $variant = $this->variant(1001);

        $this->postJson(route('cart.store'), ['variant' => $variant->id, 'grind' => 'cezve']);
        $this->postJson(route('cart.store'), ['variant' => $variant->id, 'grind' => 'cezve', 'quantity' => 2]);
        $this->postJson(route('cart.store'), ['variant' => $variant->id, 'grind' => 'espresso'])
            ->assertJson(['count' => 4]);

        $this->assertSame(3, session(Cart::SESSION_KEY)[$variant->id.'-cezve']['quantity']);
        $this->assertArrayHasKey($variant->id.'-espresso', session(Cart::SESSION_KEY));
    }

    public function test_coffee_without_a_grind_is_whole_beans()
    {
        $variant = $this->variant(1002);

        $this->postJson(route('cart.store'), ['variant' => $variant->id])->assertOk();

        $this->assertArrayHasKey($variant->id.'-whole', session(Cart::SESSION_KEY));
    }

    public function test_products_that_are_not_for_sale_cannot_be_added()
    {
        $this->postJson(route('cart.store'), ['variant' => $this->variant(3001)->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variant');

        $this->postJson(route('cart.store'), ['variant' => $this->variant(1001)->id, 'grind' => 'sand'])
            ->assertJsonValidationErrors('grind');
    }

    public function test_lines_can_be_changed_and_removed()
    {
        $key = $this->variant(2001)->id.'-whole';
        $this->postJson(route('cart.store'), ['variant' => $this->variant(2001)->id]);

        $this->patchJson(route('cart.update', $key), ['quantity' => 5])->assertJson(['count' => 5]);
        $this->patchJson(route('cart.update', $key), ['quantity' => 0])->assertJson(['count' => 0]);

        $this->postJson(route('cart.store'), ['variant' => $this->variant(2001)->id]);
        $this->deleteJson(route('cart.destroy', $key))
            ->assertJson(['count' => 0])
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'В корзине пока пусто'));
    }

    public function test_variants_that_stop_being_sold_drop_out_of_the_cart()
    {
        $variant = $this->variant(1001);
        $this->postJson(route('cart.store'), ['variant' => $variant->id]);

        $variant->update(['is_active' => false]);
        // A new request gets a new cart; tests share one container.
        $this->app->forgetScopedInstances();

        $this->getJson(route('cart.show'))->assertJson(['count' => 0]);
        $this->assertSame([], session(Cart::SESSION_KEY));
    }

    public function test_without_javascript_the_forms_land_on_the_cart_page()
    {
        $this->post(route('cart.store'), ['variant' => $this->variant(1001)->id, 'grind' => 'french'])
            ->assertRedirect(route('cart.show'));

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Кения Волк')
            ->assertSee('Для френч-пресса')
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_catalog_cards_offer_sizes_grinds_and_the_cart_button()
    {
        $this->get('/coffee')
            ->assertOk()
            ->assertSee('data-add-to-cart', false)
            ->assertSee('name="grind"', false)
            ->assertSee('В корзину');
    }
}
