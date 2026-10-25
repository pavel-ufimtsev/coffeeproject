<?php

namespace Tests\Feature\Admin;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PromoType;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Checkout\CourierRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::create([
            'token' => Str::random(40),
            'checkout_token' => Str::random(40),
            'status' => OrderStatus::New,
            'first_name' => 'Анна',
            'last_name' => 'Иванова',
            'phone' => '+79097019007',
            'email' => 'anna@example.ru',
            'delivery_method' => DeliveryMethod::Courier,
            'delivery_price' => 500,
            'city' => 'Екатеринбург',
            'street' => 'ул. Вайнера',
            'house' => '36',
            'payment_method' => PaymentMethod::Cash,
            'subtotal' => 1177,
            'total' => 1677,
            'consent_at' => now(),
            ...$attributes,
        ]);
        $order->update(['number' => 'CP-'.(10000 + $order->id)]);
        $order->items()->create(['name' => 'Кения Волк', 'variant_label' => '250 г', 'grind_label' => 'Для турки', 'price' => 1177, 'quantity' => 1, 'total' => 1177]);

        return $order;
    }

    public function test_customers_cannot_open_shop_admin_pages()
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.promo-codes.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.delivery.edit'))->assertForbidden();
    }

    public function test_orders_can_be_listed_searched_and_opened()
    {
        $anna = $this->order();
        $this->order(['last_name' => 'Петров', 'phone' => '+79120000000', 'status' => OrderStatus::Completed]);

        $this->actingAs($this->admin)->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/orders/Index')->has('orders.data', 2));

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['search' => '909 701']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.number', $anna->number));

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'completed']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.customer', 'Анна Петров'));

        $this->actingAs($this->admin)->get(route('admin.orders.show', $anna))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/Show')
                ->where('order.number', $anna->number)
                ->where('order.phone', '+7 909 701-90-07')
                ->where('order.items.0.meta', '250 г · Для турки'));
    }

    public function test_order_status_can_be_changed()
    {
        $order = $this->order();

        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), ['status' => 'shipped', 'is_paid' => true])->assertRedirect();

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $this->assertTrue($order->fresh()->is_paid);
    }

    public function test_promo_codes_can_be_managed()
    {
        $this->actingAs($this->admin)->post(route('admin.promo-codes.store'), [
            'code' => 'coffee10', 'type' => 'percent', 'value' => 10, 'ends_at' => '2026-12-31', 'is_active' => true,
        ])->assertRedirect(route('admin.promo-codes.index'));

        $promo = PromoCode::sole();
        $this->assertSame('COFFEE10', $promo->code);
        $this->assertSame(PromoType::Percent, $promo->type);
        $this->assertSame('2026-12-31 23:59:59', $promo->ends_at->format('Y-m-d H:i:s'));

        $this->actingAs($this->admin)->post(route('admin.promo-codes.store'), ['code' => 'COFFEE10', 'type' => 'fixed', 'value' => 100])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('admin.promo-codes.store'), ['code' => 'BIG', 'type' => 'percent', 'value' => 150])->assertSessionHasErrors('value');

        $this->actingAs($this->admin)->put(route('admin.promo-codes.update', $promo), ['code' => 'COFFEE10', 'type' => 'fixed', 'value' => 300, 'is_active' => false]);
        $this->assertSame(PromoType::Fixed, $promo->fresh()->type);
        $this->assertFalse($promo->fresh()->is_active);

        $this->actingAs($this->admin)->get(route('admin.promo-codes.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/promo-codes/Index')->where('promoCodes.0.value_label', "−300\u{00A0}₽"));
    }

    public function test_used_promo_codes_cannot_be_deleted()
    {
        $used = PromoCode::create(['code' => 'USED', 'type' => PromoType::Fixed, 'value' => 100]);
        $used->forceFill(['used_count' => 1])->save();
        $fresh = PromoCode::create(['code' => 'FRESH', 'type' => PromoType::Fixed, 'value' => 100]);

        $this->actingAs($this->admin)->delete(route('admin.promo-codes.destroy', $used))->assertSessionHasErrors('promo');
        $this->actingAs($this->admin)->delete(route('admin.promo-codes.destroy', $fresh))->assertRedirect(route('admin.promo-codes.index'));

        $this->assertSame(['USED'], PromoCode::pluck('code')->all());
    }

    public function test_courier_prices_can_be_changed()
    {
        $this->actingAs($this->admin)->get(route('admin.delivery.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/delivery/Edit')
                ->where('courier.price', 500)
                ->where('courier.free_from', 3000));

        $this->actingAs($this->admin)->put(route('admin.delivery.update'), ['enabled' => true, 'price' => 350, 'free_from' => 2500])->assertRedirect();

        $rates = app(CourierRates::class);
        $this->assertSame(350.0, $rates->price());
        $this->assertSame(0.0, $rates->cost(2500));
        $this->assertSame(350.0, $rates->cost(2499));

        $this->actingAs($this->admin)->put(route('admin.delivery.update'), ['enabled' => true, 'price' => 350, 'free_from' => null]);
        $this->assertSame(350.0, $rates->cost(100000));
    }
}
