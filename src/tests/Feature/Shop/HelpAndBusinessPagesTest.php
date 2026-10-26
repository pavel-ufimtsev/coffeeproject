<?php

namespace Tests\Feature\Shop;

use App\Mail\ContactMessage;
use App\Support\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HelpAndBusinessPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CDEK prices the delivery pages; never call the real API from tests.
        config([
            'services.cdek.url' => 'https://cdek.test/v2',
            'services.cdek.account' => 'account',
            'services.cdek.password' => 'secret',
            'services.cdek.yandex_maps_key' => 'maps-key',
            'services.cdek.yandex_geocoder_key' => 'geocoder-key',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://cdek.test/v2/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'https://cdek.test/v2/calculator/tariff' => Http::response(['total_sum' => 354.6, 'period_min' => 3, 'period_max' => 4]),
            'https://cdek.test/v2/deliverypoints*' => Http::response([['code' => 'MSK1'], ['code' => 'MSK2'], ['code' => 'MSK3']]),
        ]);
    }

    public function test_help_pages_open_at_the_old_addresses()
    {
        $this->get('/help')->assertOk()->assertSee('Доставка и оплата');
        $this->get('/help/delivery')->assertOk()->assertSee('<h1>Доставка и оплата</h1>', false)->assertSee('от 355');
        $this->get('/help/returns')->assertOk()->assertSee('30 дней');
        $this->get('/help/faq')->assertOk()->assertSee('"@type":"FAQPage"', false);
    }

    public function test_each_city_has_a_delivery_page_and_unknown_cities_do_not()
    {
        $this->get('/help/delivery/moskva')
            ->assertOk()
            ->assertSee('<h1>Доставка кофе в Москву</h1>', false)
            ->assertSee('3-4 дня')
            ->assertSee('<dd>3</dd>', false)
            ->assertSee('<link rel="canonical" href="'.url('/help/delivery/moskva').'">', false);

        $this->get('/help/delivery/ekaterinburg')->assertOk()->assertSee('Курьер до двери');
        $this->get('/help/delivery/atlantida')->assertNotFound();
    }

    public function test_old_addresses_without_a_page_redirect_to_the_closest_one()
    {
        $this->get('/study/aeropress')->assertStatus(301)->assertRedirect('/coffee/aeropress');
        $this->get('/more/wholesale-msk')->assertStatus(301)->assertRedirect('/wholesale');
        $this->get('/help/discount')->assertStatus(301)->assertRedirect('/help');
        $this->get('/user/login')->assertStatus(301)->assertRedirect('/login');
    }

    public function test_a_wholesale_request_reaches_the_shop_with_the_details()
    {
        Mail::fake();
        config(['shop.contact_email' => 'hello@coffeeproject.ru']);

        $this->get('/wholesale')->assertOk()->assertSee('action="'.route('wholesale.store').'"', false);

        $this->postJson('/wholesale', [
            'name' => 'Иван',
            'company' => 'Кофейня «Зерно»',
            'email' => 'ivan@example.ru',
            'phone' => '8 909 701-90-07',
            'city' => 'Пермь',
            'business' => 'cafe',
            'volume' => 'medium',
            'consent' => '1',
        ])->assertOk()->assertJsonPath('message', 'Спасибо, Иван! Скоро свяжемся с вами, пришлём прайс и предложим кофе.');

        Mail::assertQueued(ContactMessage::class, fn (ContactMessage $mail) => $mail->hasTo('hello@coffeeproject.ru')
            && $mail->data['topic'] === 'wholesale'
            && $mail->data['details']['Компания'] === 'Кофейня «Зерно»'
            && $mail->data['details']['Объём'] === '5–20 кг в месяц');
    }

    public function test_a_wholesale_request_needs_a_phone_and_a_business_type()
    {
        $this->postJson('/wholesale', ['name' => 'Иван', 'email' => 'ivan@example.ru', 'consent' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'business', 'volume']);
    }

    public function test_metrica_loads_only_with_analytics_consent()
    {
        config(['services.yandex_metrika.id' => '47060055']);

        $this->get('/')->assertSee('data-metrika="47060055"', false)->assertDontSee('data-analytics="on"', false);

        $this->withCookie(CookieConsent::COOKIE, CookieConsent::NECESSARY)->get('/')
            ->assertDontSee('data-analytics="on"', false);

        $this->withCookie(CookieConsent::COOKIE, CookieConsent::ALL)->get('/')
            ->assertSee('data-analytics="on"', false);
    }

    public function test_the_sitemap_lists_the_help_business_and_quiz_pages()
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('help.delivery.city', 'moskva'))
            ->assertSee(route('wholesale'))
            ->assertSee(route('quiz'));
    }
}
