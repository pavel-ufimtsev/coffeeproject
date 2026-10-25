<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class CatalogPagesTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCatalogFixture();
        config(['catalog.min_indexable_products' => 2]);
    }

    public function test_a_root_category_lists_its_active_products()
    {
        $this->get('/coffee')
            ->assertOk()
            ->assertSee('Кения Волк')
            ->assertSee('Бразилия Файн Кап')
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical" href="'.url('/coffee').'">', false);
    }

    public function test_archived_products_are_not_listed()
    {
        $this->get('/chaj')->assertOk()->assertDontSee('Шу Пуэр')->assertSee('Здесь пока ничего нет в продаже');
    }

    public function test_query_filters_narrow_the_list_and_are_not_indexed()
    {
        $this->get('/coffee?brew=espresso')
            ->assertOk()
            ->assertSee('Бразилия Файн Кап')
            ->assertDontSee('Кения Волк')
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="'.url('/coffee').'">', false);
    }

    public function test_options_in_one_group_widen_and_groups_narrow()
    {
        $this->get('/coffee?brew=espresso,pourover')->assertSee('Кения Волк')->assertSee('Бразилия Файн Кап');
        $this->get('/coffee?brew=espresso,pourover&taste=berry')->assertSee('Кения Волк')->assertDontSee('Бразилия Файн Кап');
    }

    public function test_filter_and_collection_landing_pages()
    {
        $this->get('/coffee/kenya')->assertOk()->assertSee('<h1>Кофе из Кении</h1>', false)->assertSee('Кения Волк')->assertDontSee('Бразилия Файн Кап');
        $this->get('/coffee/africa')->assertOk()->assertSee('Кения Волк');
        $this->get('/coffee/sale')->assertOk()->assertSee('Бразилия Файн Кап')->assertDontSee('Кения Волк');
    }

    public function test_landing_pages_with_too_few_products_are_not_indexed()
    {
        $this->get('/coffee/kenya')->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_a_product_page_shows_variants_specs_and_structured_data()
    {
        $this->get('/coffee/kenya-wolf')
            ->assertOk()
            ->assertSee('<h1 class="product__name">Кения Волк</h1>', false)
            ->assertSee('250 г')
            ->assertSee('1 кг')
            ->assertSee('Средняя обжарка')
            ->assertSee('Отправка: Екатеринбург')
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"priceCurrency":"RUB"', false);
    }

    public function test_link_previews_use_the_product_photo_or_the_brand_card()
    {
        $photo = Product::firstWhere('slug', 'kenya-wolf')->images->first()->url();

        $this->get('/coffee/kenya-wolf')
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="og:title" content="Кения Волк', false)
            ->assertSee('<meta property="og:url" content="'.url('/coffee/kenya-wolf').'">', false)
            ->assertSee('<meta property="og:image" content="'.$photo.'">', false);

        $this->get('/coffee/kenya')->assertSee('<meta property="og:image" content="'.$photo.'">', false);

        $this->get('/chaj/puer-shu')->assertSee('<meta property="og:image" content="'.asset('images/og/default.jpg').'">', false);
        $this->get('/')
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:image" content="'.asset('images/og/default.jpg').'">', false);
    }

    public function test_an_archived_product_page_stays_reachable_but_is_not_indexed()
    {
        $this->get('/chaj/puer-shu')
            ->assertOk()
            ->assertSee('Этот товар больше не продаётся')
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertDontSee('data-buy', false);
    }

    public function test_a_product_under_the_wrong_root_moves_to_its_address()
    {
        $this->get('/chaj/kenya-wolf')->assertRedirect('/coffee/kenya-wolf')->assertStatus(301);
    }

    public function test_unknown_pages_are_not_found()
    {
        $this->get('/coffee/no-such-thing')->assertNotFound();
        $this->get('/chaj/no-such-thing')->assertNotFound();
    }

    public function test_old_urls_redirect_permanently_and_count_hits()
    {
        $this->get('/moskva/coffee')->assertRedirect('/coffee')->assertStatus(301);
        $this->get('/moskva/coffee/kenya?utm_source=x')->assertRedirect('/coffee/kenya?utm_source=x');
        $this->get('/coffee/old-lot/')->assertRedirect('/coffee');

        $this->assertSame(1, Redirect::where('from_path', '/moskva/coffee')->value('hits'));
    }

    public function test_old_query_filters_redirect_to_the_new_pages()
    {
        $this->get('/coffee?countries%5B%5D=%D0%BA%D0%B5%D0%BD%D0%B8%D1%8F')->assertRedirect('/coffee/kenya');
        $this->get('/coffee?roasters%5B%5D=coffeeproject')->assertRedirect('/coffee?roaster=coffee-project');
    }

    public function test_the_sitemap_lists_indexable_pages_only()
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/coffee').'</loc>', false)
            ->assertSee('<loc>'.url('/coffee/kenya-wolf').'</loc>', false)
            ->assertDontSee(url('/coffee/kenya').'</loc>', false)
            ->assertDontSee('puer-shu');
    }

    public function test_the_yandex_feed_has_one_offer_per_variant_on_sale()
    {
        $response = $this->get('/feeds/yandex.xml')->assertOk();

        $xml = simplexml_load_string($response->getContent());

        $this->assertCount(3, $xml->shop->offers->offer);
        $this->assertSame('812', (string) $xml->xpath('//offer[@id="2001"]/oldprice')[0]);
    }

    public function test_navigation_only_links_roots_with_products_on_sale()
    {
        $this->get('/')->assertOk()->assertSee('href="'.url('/coffee').'"', false)->assertDontSee('href="'.url('/chaj').'"', false);
    }
}
