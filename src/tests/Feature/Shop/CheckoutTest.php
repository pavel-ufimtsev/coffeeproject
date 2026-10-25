<?php

namespace Tests\Feature\Shop;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Enums\PromoType;
use App\Jobs\PushOrderToRetailCrm;
use App\Mail\OrderPlaced;
use App\Models\Address;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cart\Cart;
use App\Support\Money;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCatalogFixture();
        Mail::fake();
    }

    private function variant(int $offerId): ProductVariant
    {
        return ProductVariant::where('retailcrm_offer_id', $offerId)->firstOrFail();
    }

    /**
     * Put lines in the cart: [offer ID => quantity]. 1001 is 1 177 ₽, 1002 is 4 156 ₽.
     *
     * @param  array<int, int>  $lines
     */
    private function fillCart(array $lines = [1001 => 2]): void
    {
        foreach ($lines as $offerId => $quantity) {
            $this->post(route('cart.store'), ['variant' => $this->variant($offerId)->id, 'grind' => 'cezve', 'quantity' => $quantity]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return [
            'checkout_token' => 'token-'.uniqid(),
            'phone' => '8 (909) 701-90-07',
            'email' => 'Anna@Example.ru',
            'first_name' => 'Анна',
            'last_name' => 'Иванова',
            'delivery_method' => 'courier',
            'city' => 'г. Екатеринбург',
            'street' => 'ул. Вайнера',
            'house' => '36',
            'apartment' => '28',
            'entrance' => '2',
            'floor' => '5',
            'intercom' => '28К',
            'courier_note' => 'Позвонить за час',
            'payment_method' => 'cash',
            'comment' => 'Помолоть помельче',
            'consent' => '1',
            ...$overrides,
        ];
    }

    public function test_an_empty_cart_goes_back_to_the_cart()
    {
        $this->get(route('checkout.show'))->assertRedirect(route('cart.show'));
    }

    public function test_the_cart_leads_to_checkout()
    {
        $this->fillCart();

        $this->get(route('cart.show'))->assertSee(route('checkout.show'), false);
    }

    public function test_checkout_shows_the_order_and_paid_courier_below_the_threshold()
    {
        $this->fillCart();

        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('Кения Волк')
            ->assertSee('Товары · 2 шт.')
            ->assertSee(Money::rub(2354))
            ->assertSee(Money::rub(500))
            ->assertSee(Money::rub(2854))
            ->assertSee('Добавьте товаров ещё на 646')
            ->assertSee('Наличными или картой при получении')
            ->assertSee('СДЭК по всей России');
    }

    public function test_a_guest_places_an_order()
    {
        $this->fillCart([1001 => 2, 2001 => 1]);

        $response = $this->post(route('checkout.store'), $this->details());

        $order = Order::with('items')->sole();
        $response->assertRedirect(route('checkout.complete', $order->token));

        $this->assertSame('CP-'.(10000 + $order->id), $order->number);
        $this->assertNull($order->user_id);
        $this->assertSame('+79097019007', $order->phone);
        $this->assertSame('anna@example.ru', $order->email);
        $this->assertSame('г. Екатеринбург', $order->city);
        $this->assertSame(DeliveryMethod::Courier, $order->delivery_method);
        $this->assertSame(PaymentMethod::Cash, $order->payment_method);
        $this->assertEquals(3044, $order->subtotal);
        $this->assertEquals(0, $order->delivery_price);
        $this->assertEquals(3044, $order->total);
        $this->assertNotNull($order->consent_at);

        $this->assertCount(2, $order->items);
        $line = $order->items->firstWhere('retailcrm_offer_id', 1001);
        $this->assertSame('Кения Волк', $line->name);
        $this->assertSame('250 г', $line->variant_label);
        $this->assertSame('Для турки', $line->grind_label);
        $this->assertEquals(1177, $line->price);
        $this->assertSame(2, $line->quantity);

        $this->assertSame([], session(Cart::SESSION_KEY));
        Mail::assertQueued(OrderPlaced::class, fn (OrderPlaced $mail) => $mail->hasTo('anna@example.ru'));

        $this->get(route('checkout.complete', $order->token))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Спасибо, Анна!')
            ->assertSee('+7 909 701-90-07')
            ->assertSee('подъезд 2, этаж 5, домофон 28К')
            ->assertSee('Создать аккаунт')
            ->assertSee(route('register', ['email' => 'anna@example.ru']), false);
    }

    public function test_courier_price_and_free_threshold_come_from_the_admin_settings()
    {
        $this->fillCart([1001 => 2]);

        Setting::put(['courier.price' => 350, 'courier.free_from' => 2354]);
        $this->post(route('checkout.store'), $this->details());
        $this->assertEquals(0, Order::latest('id')->first()->delivery_price);

        $this->fillCart([1001 => 2]);
        Setting::put(['courier.free_from' => 2355]);
        $this->post(route('checkout.store'), $this->details());
        $order = Order::latest('id')->first();
        $this->assertEquals(350, $order->delivery_price);
        $this->assertEquals(2704, $order->total);
    }

    public function test_prices_from_the_browser_are_ignored_and_a_changed_total_is_flagged()
    {
        $this->fillCart([1001 => 1]);

        $this->post(route('checkout.store'), $this->details(['total' => 1, 'subtotal' => 1, 'delivery_price' => 0]));
        $this->assertEquals(1677, Order::sole()->total);

        $this->fillCart([1001 => 1]);
        $this->variant(1001)->update(['price' => 1300]);

        $this->post(route('checkout.store'), $this->details(['expected_total' => 1677]))
            ->assertSessionHasErrors('total');
        $this->assertSame(1, Order::count());
    }

    public function test_the_courier_only_delivers_in_ekaterinburg()
    {
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details(['city' => 'Москва']))
            ->assertSessionHasErrors(['city' => 'Курьер доставляет только в городе Екатеринбург. Доставка по России через СДЭК появится совсем скоро.']);

        $this->assertSame(0, Order::count());
    }

    public function test_contacts_address_and_consent_are_required()
    {
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details([
            'phone' => '12345',
            'email' => 'not-an-email',
            'first_name' => '',
            'street' => '',
            'house' => '',
            'consent' => null,
        ]))->assertSessionHasErrors(['phone', 'email', 'first_name', 'street', 'house', 'consent']);

        $this->assertSame(0, Order::count());
    }

    public function test_orders_cannot_be_placed_when_cash_is_switched_off()
    {
        config(['shop.payments.cash' => false]);
        $this->fillCart();

        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('Заказ на сайте временно недоступен');

        $this->post(route('checkout.store'), $this->details())->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());
    }

    public function test_orders_cannot_be_placed_when_the_courier_is_switched_off()
    {
        Setting::put(['courier.enabled' => false]);
        $this->fillCart();

        $this->get(route('checkout.show'))->assertSee('Доставка сейчас недоступна');
        $this->post(route('checkout.store'), $this->details())->assertSessionHasErrors('delivery_method');
    }

    public function test_submitting_the_same_page_twice_places_one_order()
    {
        $this->fillCart();
        $details = $this->details();

        $first = $this->post(route('checkout.store'), $details);
        $second = $this->post(route('checkout.store'), $details);

        $order = Order::sole();
        $first->assertRedirect(route('checkout.complete', $order->token));
        $second->assertRedirect(route('checkout.complete', $order->token));
    }

    public function test_a_promo_code_takes_money_off_the_goods()
    {
        PromoCode::create(['code' => 'COFFEE10', 'type' => PromoType::Percent, 'value' => 10]);
        $this->fillCart([1001 => 3]);

        $this->postJson(route('checkout.promo.apply'), ['promo' => ' coffee10 ', 'delivery' => 'courier'])
            ->assertOk()
            // 3 531 − 353 = 3 178: still above the free threshold.
            ->assertJsonPath('total', 3178)
            ->assertJsonPath('summary', fn (string $html) => str_contains($html, 'COFFEE10') && str_contains($html, '353'));

        $this->post(route('checkout.store'), $this->details(['expected_total' => 3178]));

        $order = Order::sole();
        $this->assertSame('COFFEE10', $order->promo_code);
        $this->assertEquals(353, $order->discount);
        $this->assertEquals(0, $order->delivery_price);
        $this->assertEquals(3178, $order->total);
        $this->assertSame(1, PromoCode::sole()->used_count);
        $this->assertNull(session('checkout.promo'));
    }

    public function test_free_delivery_is_judged_after_the_discount()
    {
        PromoCode::create(['code' => 'MINUS500', 'type' => PromoType::Fixed, 'value' => 500]);
        $this->fillCart([1001 => 3]);

        // 3 531 − 500 = 3 031 goods, free; then with 1 000 off it drops below 3 000.
        $this->postJson(route('checkout.promo.apply'), ['promo' => 'MINUS500'])->assertJsonPath('total', 3031);

        PromoCode::where('code', 'MINUS500')->update(['value' => 1000]);
        $this->getJson(route('checkout.summary', ['delivery' => 'courier']))
            ->assertJsonPath('total', 3031)
            ->assertJsonPath('delivery', fn (string $html) => str_contains($html, '500'));
    }

    public function test_promo_codes_that_cannot_be_used_explain_why()
    {
        PromoCode::create(['code' => 'OLD', 'type' => PromoType::Percent, 'value' => 10, 'ends_at' => now()->subDay()]);
        PromoCode::create(['code' => 'BIG', 'type' => PromoType::Fixed, 'value' => 300, 'min_subtotal' => 5000]);
        PromoCode::create(['code' => 'GONE', 'type' => PromoType::Fixed, 'value' => 300, 'max_uses' => 1])->forceFill(['used_count' => 1])->save();
        PromoCode::create(['code' => 'OFF', 'type' => PromoType::Fixed, 'value' => 300, 'is_active' => false]);
        $this->fillCart();

        $cases = [
            'NOPE' => 'Такого промокода нет.',
            'OLD' => 'Срок действия промокода истёк.',
            'BIG' => 'Промокод действует на заказы от '.Money::rub(5000),
            'GONE' => 'Лимит использований промокода исчерпан.',
            'OFF' => 'Промокод не действует.',
        ];

        foreach ($cases as $code => $message) {
            $this->postJson(route('checkout.promo.apply'), ['promo' => $code])
                ->assertOk()
                ->assertJsonPath('summary', fn (string $html) => str_contains($html, $message));
        }

        $this->assertNull(session('checkout.promo'));
    }

    public function test_a_promo_code_used_up_before_the_order_is_refused()
    {
        $promo = PromoCode::create(['code' => 'LAST', 'type' => PromoType::Fixed, 'value' => 100, 'max_uses' => 1]);
        $this->fillCart();
        $this->postJson(route('checkout.promo.apply'), ['promo' => 'LAST'])->assertOk();

        $promo->forceFill(['used_count' => 1])->save();

        // The checkout quietly drops a code that stopped working; the total shown before no longer matches.
        $this->post(route('checkout.store'), $this->details(['expected_total' => 2754]))->assertSessionHasErrors('total');
        $this->assertSame(0, Order::count());
    }

    public function test_without_javascript_the_promo_form_returns_to_checkout()
    {
        PromoCode::create(['code' => 'COFFEE10', 'type' => PromoType::Percent, 'value' => 10]);
        $this->fillCart();

        $this->from(route('checkout.show'))->post(route('checkout.promo.apply'), ['promo' => 'nope'])
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors('promo', null, 'promo');

        $this->from(route('checkout.show'))->post(route('checkout.promo.apply'), ['promo' => 'coffee10'])->assertRedirect(route('checkout.show'));
        $this->get(route('checkout.show'))->assertSee('COFFEE10')->assertSee('Скидка по промокоду');

        $this->from(route('checkout.show'))->delete(route('checkout.promo.remove'))->assertRedirect(route('checkout.show'));
        $this->assertNull(session('checkout.promo'));
    }

    public function test_an_account_holders_first_address_is_saved_as_the_default()
    {
        $user = User::factory()->create(['name' => 'Анна Иванова']);
        $this->actingAs($user);
        $this->fillCart();

        $this->get(route('checkout.show'))
            ->assertSee('value="Анна"', false)
            ->assertSee('value="Иванова"', false)
            ->assertSee('value="'.$user->email.'"', false)
            ->assertSee('value="Дом"', false)
            ->assertDontSee('Войти');

        $this->post(route('checkout.store'), $this->details(['address_id' => 'new', 'address_label' => 'Дом']));

        $this->assertSame($user->id, Order::sole()->user_id);
        $address = $user->addresses()->sole();
        $this->assertSame('Дом', $address->label);
        $this->assertTrue($address->is_default);
        $this->assertSame('ул. Вайнера', $address->street);
        $this->assertSame('28К', $address->intercom);
    }

    public function test_editing_a_saved_address_at_checkout_updates_it()
    {
        $user = User::factory()->create();
        $home = $user->addresses()->create(['label' => 'Дом', 'city' => 'Екатеринбург', 'street' => 'ул. Ленина', 'house' => '1']);
        $work = $user->addresses()->create(['label' => 'Работа', 'city' => 'Екатеринбург', 'street' => 'ул. Малышева', 'house' => '5']);
        $this->actingAs($user);
        $this->fillCart();

        $this->get(route('checkout.show'))
            ->assertSee('value="ул. Ленина"', false)
            ->assertSee('Изменения сохранятся в адресе «Дом».');

        $this->get(route('checkout.show', ['address' => $work->id]))->assertSee('value="ул. Малышева"', false);

        $this->post(route('checkout.store'), $this->details(['address_id' => (string) $work->id, 'street' => 'ул. Малышева', 'house' => '7', 'apartment' => '12']));

        $this->assertSame('7', $work->fresh()->house);
        $this->assertSame('12', $work->fresh()->apartment);
        $this->assertSame('1', $home->fresh()->house);
        $this->assertSame(2, $user->addresses()->count());
        $this->assertSame('7', Order::sole()->house);
    }

    public function test_an_account_holder_can_add_another_address_but_not_use_someone_elses()
    {
        $user = User::factory()->create();
        $user->addresses()->create(['label' => 'Дом', 'city' => 'Екатеринбург', 'street' => 'ул. Ленина', 'house' => '1']);
        $stranger = User::factory()->create()->addresses()->create(['label' => 'Чужой', 'city' => 'Екатеринбург', 'street' => 'ул. Мира', 'house' => '2']);
        $this->actingAs($user);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details(['address_id' => (string) $stranger->id]))->assertSessionHasErrors('address_id');
        $this->post(route('checkout.store'), $this->details(['address_id' => 'new', 'address_label' => 'Дом']))->assertSessionHasErrors('address_label');

        $this->post(route('checkout.store'), $this->details(['address_id' => 'new', 'address_label' => 'Дача']))->assertSessionHasNoErrors();

        $this->assertSame(['Дом', 'Дача'], $user->addresses()->pluck('label')->all());
        $this->assertFalse($user->addresses()->where('label', 'Дача')->sole()->is_default);
        $this->assertSame('ул. Мира', $stranger->fresh()->street);
    }

    public function test_guests_cannot_pick_saved_addresses()
    {
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details(['address_id' => '1']))->assertSessionHasErrors('address_id');
    }

    public function test_logging_in_from_checkout_comes_back_to_checkout()
    {
        $user = User::factory()->create();
        $this->fillCart();

        $this->get(route('checkout.login'))->assertRedirect(route('login'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('checkout.show'));

        $this->get(route('checkout.show'))->assertOk()->assertSee('Кения Волк');
    }

    public function test_orders_go_to_retailcrm_only_when_switched_on()
    {
        Queue::fake();

        $this->fillCart();
        $this->post(route('checkout.store'), $this->details());
        Queue::assertNotPushed(PushOrderToRetailCrm::class);

        config(['services.retailcrm.push_orders' => true]);
        $this->fillCart();
        $this->post(route('checkout.store'), $this->details());
        Queue::assertPushed(PushOrderToRetailCrm::class, 1);
    }

    public function test_the_retailcrm_order_carries_delivery_payment_items_and_discount()
    {
        PromoCode::create(['code' => 'COFFEE10', 'type' => PromoType::Percent, 'value' => 10]);
        $this->fillCart([1001 => 2]);
        $this->postJson(route('checkout.promo.apply'), ['promo' => 'COFFEE10']);
        $this->post(route('checkout.store'), $this->details(['floor' => 'цоколь']));

        $payload = (new PushOrderToRetailCrm(Order::sole()))->payload();

        $this->assertSame(Order::sole()->number, $payload['externalId']);
        $this->assertSame('shopping-cart', $payload['orderMethod']);
        $this->assertSame('+79097019007', $payload['phone']);
        $this->assertSame('courier', $payload['delivery']['code']);
        $this->assertEquals(500, $payload['delivery']['cost']);
        $this->assertSame('ул. Вайнера', $payload['delivery']['address']['street']);
        $this->assertSame(2, $payload['delivery']['address']['block']);
        $this->assertArrayNotHasKey('floor', $payload['delivery']['address']);
        $this->assertStringContainsString('этаж цоколь', $payload['delivery']['address']['notes']);
        $this->assertSame([['type' => 'cash']], $payload['payments']);
        $this->assertEquals(235, $payload['discountManualAmount']);
        $this->assertSame(['id' => 1001], $payload['items'][0]['offer']);
        $this->assertSame('Для турки', $payload['items'][0]['properties'][0]['value']);
        $this->assertSame('Помолоть помельче', $payload['customerComment']);
    }

    public function test_guest_orders_join_the_account_once_the_email_is_verified()
    {
        $this->fillCart();
        $this->post(route('checkout.store'), $this->details(['email' => 'anna@example.ru']));

        $user = User::factory()->create(['email' => 'anna@example.ru']);
        event(new Verified($user));

        $this->assertSame($user->id, Order::sole()->user_id);
    }

    public function test_the_thank_you_page_needs_the_secret_token()
    {
        $this->fillCart();
        $this->post(route('checkout.store'), $this->details());

        $this->get('/checkout/complete/'.Order::sole()->number)->assertNotFound();
    }

    public function test_the_address_book_limit_applies_at_checkout()
    {
        $user = User::factory()->create();

        foreach (range(1, Address::MAX_PER_USER) as $i) {
            $user->addresses()->create(['label' => "Адрес {$i}", 'city' => 'Екатеринбург', 'street' => 'ул. Ленина', 'house' => (string) $i]);
        }

        $this->actingAs($user);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details(['address_id' => 'new', 'address_label' => 'Ещё один']))->assertSessionHasErrors('address_label');
    }
}
