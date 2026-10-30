<?php

namespace Tests\Feature\Admin;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Mail\OrderPlaced;
use App\Mail\Welcome;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'admin@coffeeproject.ru']);
    }

    private function order(): Order
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
        ]);
        $order->update(['number' => 'CP-'.(10000 + $order->id)]);
        $order->items()->create(['name' => 'Кения Волк', 'variant_label' => '250 г', 'grind_label' => 'Для турки', 'price' => 1177, 'quantity' => 1, 'total' => 1177]);

        return $order;
    }

    public function test_customers_cannot_open_the_emails_page()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.emails.edit'))
            ->assertForbidden();
    }

    public function test_the_page_lists_both_emails_with_their_default_texts()
    {
        $this->actingAs($this->admin)
            ->get(route('admin.emails.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/emails/Edit')
                ->has('templates', 2)
                ->where('templates.0.key', 'welcome')
                ->where('templates.0.texts.subject', 'Добро пожаловать в {shop}')
                ->where('templates.1.key', 'order_placed'));
    }

    public function test_saved_texts_are_used_in_the_order_email()
    {
        $this->actingAs($this->admin)
            ->put(route('admin.emails.update', 'order_placed'), [
                'subject' => 'Ваш заказ {number} у нас',
                'heading' => 'Ура, {name}!',
                'intro' => "Сумма заказа {total}.\n\nЗвоните <b>в любое время</b>.",
                'outro' => null,
            ])
            ->assertRedirect();

        $order = $this->order();
        $mail = new OrderPlaced($order);

        $mail->assertHasSubject("Ваш заказ {$order->number} у нас");
        $mail->assertSeeInHtml('Ура, Анна!');
        $mail->assertSeeInHtml("Сумма заказа 1\u{202F}677\u{00A0}₽.", false);
        $mail->assertSeeInHtml('&lt;b&gt;в любое время&lt;/b&gt;', false);
        $mail->assertSeeInHtml('Кения Волк');
        $mail->assertSeeInHtml('250 г · Для турки');
        $mail->assertSeeInHtml(route('checkout.complete', $order->token));
        $mail->assertSeeInText('Ура, Анна!');
        $mail->assertSeeInText('Кения Волк');
    }

    public function test_the_subject_and_heading_are_required()
    {
        $this->actingAs($this->admin)
            ->put(route('admin.emails.update', 'welcome'), ['subject' => '', 'heading' => ''])
            ->assertSessionHasErrors(['subject', 'heading']);
    }

    public function test_unknown_templates_are_not_found()
    {
        $this->actingAs($this->admin)
            ->put(route('admin.emails.update', 'nope'), ['subject' => 'a', 'heading' => 'b'])
            ->assertNotFound();
    }

    public function test_previews_render_with_and_without_orders()
    {
        $this->actingAs($this->admin)
            ->get(route('admin.emails.preview', 'order_placed'))
            ->assertOk()
            ->assertSee('CP-10001');

        $order = $this->order();
        $order->update(['confirmed_at' => now()]);

        $this->get(route('admin.emails.preview', 'order_placed'))
            ->assertOk()
            ->assertSee($order->number);

        $this->get(route('admin.emails.preview', 'welcome'))
            ->assertOk()
            ->assertSee('Здравствуйте, '.$this->admin->name.'!');
    }

    public function test_a_test_email_goes_to_the_admin()
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.emails.test', 'welcome'))
            ->assertRedirect();

        Mail::assertSent(Welcome::class, fn (Welcome $mail) => $mail->hasTo('admin@coffeeproject.ru'));
    }

    public function test_new_customers_get_the_welcome_email()
    {
        Mail::fake();

        $this->withSession([CreateNewUser::SHOWN_AT => now()->subMinute()->timestamp])->post(route('register.store'), [
            'name' => 'Анна',
            'email' => 'anna@example.ru',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Mail::assertQueued(Welcome::class, fn (Welcome $mail) => $mail->hasTo('anna@example.ru'));
        (new Welcome(User::firstWhere('email', 'anna@example.ru')))
            ->assertHasSubject('Добро пожаловать в Coffee Project')
            ->assertSeeInHtml('Здравствуйте, Анна!');
    }
}
