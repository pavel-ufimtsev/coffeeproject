<?php

namespace Tests\Feature\Shop;

use App\Enums\DeliveryMethod;
use App\Jobs\PushOrderToRetailCrm;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Cdek\Cdek;
use App\Services\TBank\TBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class CdekCheckoutTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    private const API = 'https://cdek.test/v2';

    /** CDEK's answer for MSK123 (a pickup point) and MSK900 (a parcel locker), by code. */
    private const POINTS = [
        'MSK123' => ['code' => 'MSK123', 'name' => 'На Тверской', 'type' => 'PVZ', 'work_time' => 'Пн-Пт 10:00-20:00', 'location' => ['city_code' => 44, 'city' => 'Москва', 'address' => 'ул. Тверская, 1']],
        'MSK900' => ['code' => 'MSK900', 'name' => 'Постамат у метро', 'type' => 'POSTAMAT', 'location' => ['city_code' => 44, 'city' => 'Москва', 'address' => 'ул. Арбат, 5']],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shop.payments.tbank' => true,
            'services.tbank.test' => ['terminal_key' => '1234567890DEMO', 'password' => 'test-password'],
            'services.cdek' => ['url' => self::API, 'account' => 'account', 'password' => 'secret', 'yandex_maps_key' => 'maps-key', 'yandex_geocoder_key' => 'geocoder-key', 'from_city_code' => 250, 'map' => 'widget'],
        ]);
        $this->app->forgetInstance(TBank::class);
        $this->app->forgetInstance(Cdek::class);

        Http::fake([
            self::API.'/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::API.'/deliverypoints/byPolygons*' => Http::response([self::POINTS['MSK123']]),
            self::API.'/deliverypoints*' => fn (Request $request) => Http::response(
                isset($request->data()['code']) ? array_values(array_filter([self::POINTS[$request->data()['code']] ?? null])) : array_values(self::POINTS),
                200,
                ['X-Total-Elements' => '2', 'X-Secret' => 'not for the browser'],
            ),
            self::API.'/calculator/tariff' => fn (Request $request) => Http::response(
                ['total_sum' => $request->data()['tariff_code'] === 368 ? 299.0 : 389.4, 'period_min' => 2, 'period_max' => 4],
            ),
            self::API.'/calculator/tarifflist' => Http::response(['tariff_codes' => [['tariff_code' => 136, 'delivery_mode' => 4, 'delivery_sum' => 389.4]]]),
            'https://geocode-maps.yandex.ru/1.x/*' => Http::response(['response' => ['GeoObjectCollection' => ['featureMember' => []]]]),
            'https://securepay.tinkoff.ru/v2/Init' => Http::response(['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW', 'PaymentId' => '700001', 'PaymentURL' => 'https://pay.tbank.ru/700001']),
        ]);

        $this->importCatalogFixture();
        Mail::fake();
    }

    /**
     * @param  array<int, int>  $lines  offer ID => quantity; 1001 is 1 177 ₽ and weighs 250 g
     */
    private function fillCart(array $lines = [1001 => 2]): void
    {
        foreach ($lines as $offerId => $quantity) {
            $variant = ProductVariant::where('retailcrm_offer_id', $offerId)->firstOrFail();
            $this->post(route('cart.store'), ['variant' => $variant->id, 'grind' => 'cezve', 'quantity' => $quantity]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return [
            'checkout_token' => 'token-'.uniqid(),
            'phone' => '+7 909 701-90-07',
            'email' => 'anna@example.ru',
            'first_name' => 'Анна',
            'last_name' => 'Иванова',
            'delivery_method' => 'cdek',
            'pickup_point' => 'MSK123',
            'payment_method' => 'tbank',
            'consent' => '1',
            ...$overrides,
        ];
    }

    public function test_cdek_is_offered_only_when_set_up_and_card_payment_is_on()
    {
        $this->fillCart();

        $this->get(route('checkout.show'))->assertOk()->assertSee('value="cdek"', false)->assertSee('data-cdek-map', false);

        config(['services.cdek.yandex_maps_key' => null]);
        $this->get(route('checkout.show'))->assertDontSee('value="cdek"', false)->assertSee('Скоро');

        config(['services.cdek.yandex_maps_key' => 'maps-key', 'services.cdek.yandex_geocoder_key' => null]);
        $this->get(route('checkout.show'))->assertDontSee('value="cdek"', false);

        config(['services.cdek.yandex_geocoder_key' => 'geocoder-key', 'shop.payments.tbank' => false]);
        $this->get(route('checkout.show'))->assertDontSee('value="cdek"', false);

        config(['shop.payments.tbank' => true]);
        Setting::put(['cdek.enabled' => false]);
        $this->get(route('checkout.show'))->assertDontSee('value="cdek"', false);
    }

    public function test_cdek_map_picks_the_cdek_widget_or_our_yandex_map()
    {
        $this->fillCart();

        $this->get(route('checkout.show'))
            ->assertSee('&quot;map&quot;:&quot;widget&quot;', false)
            ->assertDontSee('data-pickup-map', false);

        config(['services.cdek.map' => 'yandex21']);

        $this->get(route('checkout.show'))
            ->assertSee('&quot;map&quot;:&quot;yandex21&quot;', false)
            ->assertSee('data-pickup-map', false)
            ->assertSee('Пункты выдачи СДЭК');
    }

    public function test_the_summary_asks_for_a_point_then_adds_the_cdek_price()
    {
        $this->fillCart();

        $this->getJson(route('checkout.summary', ['delivery' => 'cdek']))
            ->assertOk()
            ->assertJson(['deliveryReady' => false, 'total' => 2354])
            ->assertJsonPath('delivery', fn (string $html) => str_contains($html, 'Выбрать пункт выдачи'));

        $this->getJson(route('checkout.summary', ['delivery' => 'cdek', 'pickup_point' => 'MSK123']))
            ->assertJson(['deliveryReady' => true, 'total' => 2354 + 390])
            ->assertJsonPath('delivery', fn (string $html) => str_contains($html, 'ул. Тверская, 1') && str_contains($html, '2-4 дня'));

        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/calculator/tariff'
            && $request['tariff_code'] === 136
            && $request['from_location'] === ['code' => 250]
            && $request['to_location'] === ['code' => 44]
            && $request['packages'] === [['weight' => 2 * 250 + 150, 'length' => 30, 'width' => 20, 'height' => 12]]);
    }

    public function test_the_point_and_price_come_back_from_a_serializing_cache()
    {
        config(['cache.default' => 'database']);
        $this->fillCart();

        foreach ([1, 2] as $_) {
            $this->getJson(route('checkout.summary', ['delivery' => 'cdek', 'pickup_point' => 'MSK123']))
                ->assertOk()
                ->assertJson(['deliveryReady' => true, 'total' => 2354 + 390]);
        }

        Http::assertSentCount(3);
    }

    public function test_a_cdek_order_is_priced_on_the_server_and_goes_to_payment()
    {
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details(['expected_total' => 2354 + 390]))
            ->assertRedirect('https://pay.tbank.ru/700001');

        $order = Order::sole();
        $this->assertSame(DeliveryMethod::Cdek, $order->delivery_method);
        $this->assertEquals(390, $order->delivery_price);
        $this->assertEquals(2354 + 390, $order->total);
        $this->assertSame('MSK123', $order->pickup_point_code);
        $this->assertSame('Москва', $order->city);
        $this->assertSame('ул. Тверская, 1', $order->street);
        $this->assertNull($order->house);
        $this->assertSame(136, $order->delivery_tariff);
        $this->assertSame('Пункт выдачи СДЭК MSK123, Москва, ул. Тверская, 1', $order->destinationLine());
    }

    public function test_a_parcel_locker_uses_the_locker_tariff()
    {
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details(['pickup_point' => 'MSK900']));

        $this->assertEquals(299, Order::sole()->delivery_price);
        $this->assertSame(368, Order::sole()->delivery_tariff);
        $this->assertStringStartsWith('Постамат СДЭК MSK900', Order::sole()->destinationLine());
    }

    public function test_cdek_orders_need_a_real_point_and_card_payment()
    {
        config(['shop.payments.cash' => true]);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details(['pickup_point' => '']))->assertSessionHasErrors(['pickup_point' => 'Выберите пункт выдачи на карте.']);
        $this->post(route('checkout.store'), $this->details(['pickup_point' => 'NOPE1']))->assertSessionHasErrors('pickup_point');
        $this->post(route('checkout.store'), $this->details(['payment_method' => 'cash']))->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Order::count());
    }

    public function test_cdek_delivery_is_free_from_its_own_threshold()
    {
        Setting::put(['cdek.free_from' => 2000]);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details());

        $this->assertEquals(0, Order::sole()->delivery_price);
        $this->assertEquals(2354, Order::sole()->total);
    }

    public function test_the_retailcrm_order_names_the_pickup_point()
    {
        $this->fillCart();
        $this->post(route('checkout.store'), $this->details());

        $payload = (new PushOrderToRetailCrm(Order::sole()))->payload();

        $this->assertSame('sdek-v-2-01f0f8b2889c8e17ac733e519dc2b44f', $payload['delivery']['code']);
        $this->assertEquals(390, $payload['delivery']['cost']);
        $this->assertSame(['pickuppointId' => 'MSK123', 'tariff' => '136'], $payload['delivery']['data']);
        $this->assertSame('Москва', $payload['delivery']['address']['city']);
    }

    public function test_the_map_service_passes_on_only_what_the_map_needs()
    {
        $this->fillCart();

        $this->getJson(route('checkout.cdek', ['action' => 'offices', 'city_code' => 44, 'page' => 0, 'size' => 500, 'grant_type' => 'x']))
            ->assertOk()
            ->assertHeader('X-Service-Version', '4.1.0')
            ->assertHeader('X-Total-Elements', '2')
            ->assertHeaderMissing('X-Secret')
            ->assertJsonCount(2);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::API.'/deliverypoints?')
            && $request['city_code'] === '44' && $request['is_handout'] === 'true' && ! isset($request['grant_type']));

        // The browser's sender city and parcel are replaced by ours.
        $this->postJson(route('checkout.cdek'), [
            'action' => 'calculate',
            'from_location' => ['code' => 1],
            'to_location' => ['code' => 44],
            'packages' => [['weight' => 1, 'length' => 1, 'width' => 1, 'height' => 1]],
        ])->assertOk()->assertJsonPath('tariff_codes.0.tariff_code', 136);

        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/calculator/tarifflist'
            && $request['from_location'] === ['code' => 250]
            && $request['to_location'] === ['code' => 44]
            && $request['packages'][0]['weight'] === 650);

        $this->getJson(route('checkout.cdek', ['action' => 'orders']))->assertStatus(400);
    }

    public function test_map_address_lookups_go_to_yandex_with_the_server_key()
    {
        foreach ([1, 2] as $_) {
            $this->getJson(route('checkout.geocode', ['geocode' => 'Екатеринбург', 'apikey' => 'browser-key', 'callback' => 'x']))
                ->assertOk()
                ->assertJsonPath('response.GeoObjectCollection.featureMember', []);
        }

        // Asked once, then answered from the cache.
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://geocode-maps.yandex.ru/1.x/?')
            && $request['apikey'] === 'geocoder-key'
            && $request['geocode'] === 'Екатеринбург'
            && ! isset($request['callback']));

        $this->getJson(route('checkout.geocode'))->assertUnprocessable();
        $this->getJson(route('checkout.geocode', ['geocode' => 'Москва', 'results' => 500]))->assertUnprocessable();
    }
}
