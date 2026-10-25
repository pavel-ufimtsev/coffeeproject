<?php

namespace Tests\Feature\Shop;

use App\Enums\PaymentMethod;
use App\Enums\PromoType;
use App\Jobs\PushOrderToRetailCrm;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Services\TBank\CardPayment;
use App\Services\TBank\TBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class TBankPaymentTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    private const API = 'https://securepay.tinkoff.ru/v2';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shop.payments.tbank' => true,
            'services.tbank.test' => ['terminal_key' => '1234567890DEMO', 'password' => 'test-password'],
            'services.tbank.live' => ['terminal_key' => 'LiveTerminal', 'password' => 'live-password'],
        ]);
        $this->app->forgetInstance(TBank::class);

        $this->importCatalogFixture();
        Mail::fake();
    }

    private function bank(): TBank
    {
        return app(TBank::class);
    }

    /**
     * @param  array<int, int>  $lines  offer ID => quantity; 1001 is 1 177 ₽
     */
    private function fillCart(array $lines = [1001 => 2]): void
    {
        foreach ($lines as $offerId => $quantity) {
            $variant = ProductVariant::where('retailcrm_offer_id', $offerId)->firstOrFail();
            $this->post(route('cart.store'), ['variant' => $variant->id, 'grind' => 'cezve', 'quantity' => $quantity]);
        }
    }

    private function details(array $overrides = []): array
    {
        return [
            'checkout_token' => 'token-'.uniqid(),
            'phone' => '+7 909 701-90-07',
            'email' => 'anna@example.ru',
            'first_name' => 'Анна',
            'last_name' => 'Иванова',
            'delivery_method' => 'courier',
            'city' => 'Екатеринбург',
            'street' => 'ул. Вайнера',
            'house' => '36',
            'payment_method' => 'tbank',
            'consent' => '1',
            ...$overrides,
        ];
    }

    /**
     * Replace the faked bank responses (Http::fake() alone adds to the earlier ones).
     *
     * @param  array<string, mixed>  $responses
     */
    private function refake(array $responses): void
    {
        $this->app->forgetInstance(Factory::class);
        Http::clearResolvedInstances();
        Http::fake($responses);
    }

    private function fakeInit(string $paymentId = '700001'): void
    {
        $this->refake([
            self::API.'/Init' => Http::response(['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW', 'PaymentId' => $paymentId, 'PaymentURL' => "https://pay.tbank.ru/{$paymentId}"]),
            self::API.'/GetState' => Http::response(['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW', 'PaymentId' => $paymentId, 'Amount' => 0]),
        ]);
    }

    /**
     * Place a card order (2 × 1 177 ₽ + 500 ₽ delivery = 2 854 ₽) and return it.
     */
    private function placeCardOrder(array $lines = [1001 => 2]): Order
    {
        $this->fakeInit();
        $this->fillCart($lines);
        $this->post(route('checkout.store'), $this->details());

        return Order::latest('id')->firstOrFail();
    }

    /**
     * A notification as T-Bank sends it, signed with the terminal password.
     */
    private function notify(Order $order, string $status, ?int $amount = null, array $overrides = []): TestResponse
    {
        $notification = [
            'TerminalKey' => '1234567890DEMO',
            'OrderId' => $order->number,
            'Success' => $status !== 'REJECTED',
            'Status' => $status,
            'PaymentId' => (int) $order->payment_id,
            'ErrorCode' => '0',
            'Amount' => $amount ?? (int) round($order->total * 100),
            'CardId' => 12345,
            'Pan' => '430000******0777',
            'ExpDate' => '1230',
            ...$overrides,
        ];
        $notification['Token'] ??= $this->bank()->token($notification);

        return $this->postJson('/api/tinkoff/webhook', $notification);
    }

    public function test_tokens_match_the_examples_in_the_t_bank_docs()
    {
        $init = new TBank(self::API, 'test', ['terminal_key' => 'MerchantTerminalKey', 'password' => '11111111111111']);
        $this->assertSame('72dd466f8ace0a37a1f740ce5fb78101712bc0665d91a8108c7c8a0ccd426db2', $init->token([
            'TerminalKey' => 'MerchantTerminalKey',
            'Amount' => 19200,
            'OrderId' => '00000',
            'Description' => 'Подарочная карта на 1000 рублей',
            'DATA' => ['Phone' => '+71234567890', 'Email' => 'a@test.com'],
            'Receipt' => ['Taxation' => 'osn', 'Items' => []],
        ]));

        $notification = new TBank(self::API, 'test', ['terminal_key' => '1234567890DEMO', 'password' => '11111111111']);
        $this->assertSame('1c0964277d0213349243065a0d5b838b8e90d2d25f740d0f2767836e710e80c8', $notification->token([
            'TerminalKey' => '1234567890DEMO', 'OrderId' => '000000', 'Success' => true, 'Status' => 'AUTHORIZED',
            'PaymentId' => '0000000', 'ErrorCode' => '0', 'Amount' => '1111', 'CardId' => '000000',
            'Pan' => '200000******0000', 'ExpDate' => '1111', 'RebillId' => '000000',
        ]));
    }

    public function test_the_mode_picks_the_test_or_live_terminal()
    {
        $this->assertSame('test', $this->bank()->mode());

        config(['services.tbank.mode' => 'live']);
        $this->app->forgetInstance(TBank::class);
        $this->assertSame('live', $this->bank()->mode());

        $this->fakeInit();
        $this->fillCart();
        $this->post(route('checkout.store'), $this->details());

        Http::assertSent(fn (Request $request) => $request['TerminalKey'] === 'LiveTerminal');
    }

    public function test_card_payment_is_offered_only_with_a_terminal()
    {
        $this->fillCart();
        $this->get(route('checkout.show'))->assertSee('Картой, через СБП или T-Pay')->assertSee('value="tbank"', false);

        config(['services.tbank.test' => ['terminal_key' => null, 'password' => null]]);
        $this->app->forgetInstance(TBank::class);

        $this->assertNotContains(PaymentMethod::TBank, PaymentMethod::enabled());
        $this->get(route('checkout.show'))->assertDontSee('value="tbank"', false);
        $this->post(route('checkout.store'), $this->details())->assertSessionHasErrors('payment_method');
    }

    public function test_a_card_order_goes_to_the_bank_and_waits_for_payment()
    {
        Queue::fake();
        config(['services.retailcrm.push_orders' => true]);
        $this->fakeInit('700001');
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details())->assertRedirect('https://pay.tbank.ru/700001');

        $order = Order::sole();
        $this->assertSame(PaymentMethod::TBank, $order->payment_method);
        $this->assertSame('700001', $order->payment_id);
        $this->assertSame(1, $order->payment_attempts);
        $this->assertFalse($order->is_paid);
        $this->assertNull($order->confirmed_at);
        Mail::assertNothingQueued();
        Queue::assertNotPushed(PushOrderToRetailCrm::class);

        Http::assertSent(function (Request $request) use ($order) {
            return $request->url() === self::API.'/Init'
                && $request['Amount'] === 285400
                && $request['OrderId'] === $order->number
                && $request['SuccessURL'] === route('payment.success', ['order' => $order->token])
                && $request['FailURL'] === route('payment.failed', ['order' => $order->token])
                && $request['NotificationURL'] === url('/api/tinkoff/webhook')
                && $request['DATA'] === ['Email' => 'anna@example.ru', 'Phone' => '+79097019007']
                && ! isset($request['Receipt'])
                && $request['Token'] === $this->bank()->token($request->data());
        });
    }

    public function test_a_confirmed_notification_marks_the_order_paid_once()
    {
        Queue::fake();
        config(['services.retailcrm.push_orders' => true]);
        $order = $this->placeCardOrder();

        $this->notify($order, 'AUTHORIZED')->assertOk()->assertContent('OK');
        $this->assertFalse($order->fresh()->is_paid);

        $this->notify($order, 'CONFIRMED')->assertOk()->assertContent('OK');
        $this->notify($order, 'CONFIRMED')->assertOk();
        // AUTHORIZED and CONFIRMED are sent together and may arrive out of order.
        $this->notify($order, 'AUTHORIZED')->assertOk();

        $order->refresh();
        $this->assertTrue($order->is_paid);
        $this->assertSame('CONFIRMED', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->confirmed_at);
        Mail::assertQueued(OrderPlaced::class, 1);
        Queue::assertPushed(PushOrderToRetailCrm::class, 1);

        $payload = (new PushOrderToRetailCrm($order))->payload();
        $this->assertSame([['type' => 'tinkoff', 'status' => 'paid', 'amount' => 2854.0, 'externalId' => $order->payment_id]], $payload['payments']);
    }

    public function test_notifications_with_a_bad_token_or_wrong_amount_are_ignored()
    {
        $order = $this->placeCardOrder();

        $this->notify($order, 'CONFIRMED', overrides: ['Token' => str_repeat('0', 64)])->assertForbidden();
        $this->notify($order, 'CONFIRMED', overrides: ['TerminalKey' => 'SomeoneElse'])->assertForbidden();
        $this->notify($order, 'CONFIRMED', amount: 100)->assertOk();

        $this->assertFalse($order->fresh()->is_paid);
        Mail::assertNothingQueued();
    }

    public function test_coming_back_from_the_bank_checks_the_payment()
    {
        $order = $this->placeCardOrder();
        $this->refake([self::API.'/GetState' => Http::response(['Success' => true, 'ErrorCode' => '0', 'Status' => 'CONFIRMED', 'PaymentId' => $order->payment_id, 'Amount' => 285400])]);

        $this->get(route('payment.success', ['order' => $order->token]))->assertRedirect(route('checkout.complete', $order->token));

        $this->assertTrue($order->fresh()->is_paid);
        Mail::assertQueued(OrderPlaced::class, 1);
        $this->get(route('checkout.complete', $order->token))->assertSee('Оплата прошла, спасибо')->assertSee('Оплачен');
    }

    public function test_a_failed_payment_can_be_tried_again_or_paid_on_delivery()
    {
        $order = $this->placeCardOrder();
        $this->refake([
            self::API.'/GetState' => Http::response(['Success' => true, 'ErrorCode' => '0', 'Status' => 'REJECTED', 'PaymentId' => $order->payment_id, 'Amount' => 285400]),
            self::API.'/Init' => Http::response(['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW', 'PaymentId' => '700002', 'PaymentURL' => 'https://pay.tbank.ru/700002']),
        ]);

        $this->get(route('payment.failed', ['order' => $order->token]))
            ->assertOk()
            ->assertSee('Оплата не прошла')
            ->assertSee('Оплатить при получении')
            ->assertSee(route('payment.retry', $order->token), false);
        $this->assertSame('REJECTED', $order->fresh()->payment_status);

        $this->post(route('payment.retry', $order->token))->assertRedirect('https://pay.tbank.ru/700002');
        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/Init' && $request['OrderId'] === "{$order->number}-2");
        $this->assertSame('700002', $order->fresh()->payment_id);

        $this->post(route('payment.cash', $order->token))->assertRedirect(route('checkout.complete', $order->token));
        $order->refresh();
        $this->assertSame(PaymentMethod::Cash, $order->payment_method);
        $this->assertNotNull($order->confirmed_at);
        Mail::assertQueued(OrderPlaced::class, 1);
    }

    public function test_a_late_notification_about_an_older_attempt_does_not_overwrite_the_latest()
    {
        $order = $this->placeCardOrder();
        $order->forceFill(['payment_id' => '700002', 'payment_attempts' => 2])->save();

        $this->notify($order, 'REJECTED', overrides: ['PaymentId' => 700001])->assertOk();
        $this->assertSame('700002', $order->fresh()->payment_id);
        $this->assertSame('NEW', $order->fresh()->payment_status);

        // But if the older attempt was paid after all, that counts.
        $this->notify($order, 'CONFIRMED', overrides: ['PaymentId' => 700001])->assertOk();
        $this->assertTrue($order->fresh()->is_paid);
    }

    public function test_when_the_bank_cannot_start_the_payment_the_customer_can_try_again()
    {
        $this->refake([
            self::API.'/Init' => Http::response(['Success' => false, 'ErrorCode' => '204', 'Message' => 'Неверный токен']),
            self::API.'/GetState' => Http::response(['Success' => false, 'ErrorCode' => '7', 'Message' => 'Платёж не найден']),
        ]);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->details())->assertRedirect();
        $order = Order::sole();

        $this->get(route('payment.failed', ['order' => $order->token]))->assertOk()->assertSee('Оплатить');
        $this->assertSame(0, $order->fresh()->payment_attempts);
    }

    public function test_paid_orders_cannot_be_switched_or_paid_again()
    {
        $order = $this->placeCardOrder();
        $this->notify($order, 'CONFIRMED');

        $this->post(route('payment.retry', $order->token))->assertRedirect(route('checkout.complete', $order->token));
        $this->post(route('payment.cash', $order->token))->assertRedirect(route('checkout.complete', $order->token));
        $this->get(route('payment.failed', ['order' => $order->token]))->assertRedirect(route('checkout.complete', $order->token));

        $this->assertSame(PaymentMethod::TBank, $order->fresh()->payment_method);
        Http::assertSentCount(1);
    }

    public function test_the_receipt_adds_up_to_the_payment_to_the_kopeck()
    {
        config(['services.tbank.receipts' => true, 'services.tbank.vat' => 'vat22', 'services.tbank.taxation' => 'osn']);
        PromoCode::create(['code' => 'COFFEE10', 'type' => PromoType::Percent, 'value' => 10]);
        $this->fakeInit();
        $this->fillCart([1001 => 3]);
        $this->postJson(route('checkout.promo.apply'), ['promo' => 'COFFEE10']);
        $this->post(route('checkout.store'), $this->details());

        $sent = Http::recorded(fn (Request $request) => $request->url() === self::API.'/Init')[0][0];
        $receipt = $sent['Receipt'];

        // 3 × 1 177 = 3 531 ₽, minus 353 ₽ = 3 178 ₽, which does not divide by 3: two lines.
        $this->assertSame(317800, $sent['Amount']);
        $this->assertSame(317800, array_sum(array_column($receipt['Items'], 'Amount')));
        $this->assertSame([2, 1], array_column($receipt['Items'], 'Quantity'));
        $this->assertSame([105933, 105934], array_column($receipt['Items'], 'Price'));
        $this->assertSame('1.2', $receipt['FfdVersion']);
        $this->assertSame('osn', $receipt['Taxation']);
        $this->assertSame('vat22', $receipt['Items'][0]['Tax']);
        $this->assertSame('шт', $receipt['Items'][0]['MeasurementUnit']);
        $this->assertSame('anna@example.ru', $receipt['Email']);
    }

    public function test_paid_delivery_is_a_service_line_in_the_receipt()
    {
        config(['services.tbank.receipts' => true]);
        $order = $this->placeCardOrder();

        $items = app(CardPayment::class)->receipt($order)['Items'];

        $this->assertSame(['commodity', 'service'], array_column($items, 'PaymentObject'));
        $this->assertSame(50000, $items[1]['Amount']);
        $this->assertSame(285400, array_sum(array_column($items, 'Amount')));
    }
}
