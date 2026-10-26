<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Mail\OrderPlaced;
use App\Mail\Welcome;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\EmailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Texts of the emails customers get, with a preview and a test send.
 */
class EmailController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/emails/Edit', [
            'templates' => collect(EmailTemplates::all())->map(fn (array $template, string $key) => [
                ...$template,
                'key' => $key,
                'texts' => EmailTemplates::get($key),
                'defaults' => EmailTemplates::defaults($key),
            ])->values(),
        ]);
    }

    public function update(Request $request, string $template): RedirectResponse
    {
        $this->ensureExists($template);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'heading' => ['required', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'outro' => ['nullable', 'string', 'max:5000'],
        ]);

        EmailTemplates::put($template, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email saved.')]);

        return back();
    }

    /**
     * The email as the customer sees it, for the iframe on the edit page.
     */
    public function preview(string $template): string
    {
        $this->ensureExists($template);

        return $this->mailable($template, auth()->user())->render();
    }

    /**
     * Send the saved email to the admin's own address.
     */
    public function test(Request $request, string $template): RedirectResponse
    {
        $this->ensureExists($template);

        Mail::to($request->user())->send($this->mailable($template, $request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent to :email.', ['email' => $request->user()->email])]);

        return back();
    }

    private function ensureExists(string $template): void
    {
        abort_unless(array_key_exists($template, EmailTemplates::all()), 404);
    }

    private function mailable(string $template, User $user): Mailable
    {
        return match ($template) {
            'welcome' => new Welcome($user),
            'order_placed' => new OrderPlaced(Order::with('items')->whereNotNull('confirmed_at')->latest('id')->first() ?? $this->sampleOrder()),
        };
    }

    /**
     * An unsaved order to show the confirmation before any real one exists.
     */
    private function sampleOrder(): Order
    {
        $order = new Order([
            'number' => 'CP-10001',
            'token' => Str::random(40),
            'status' => OrderStatus::New,
            'first_name' => 'Анна',
            'last_name' => 'Иванова',
            'phone' => '+79097019007',
            'email' => 'anna@example.ru',
            'delivery_method' => DeliveryMethod::Courier,
            'delivery_price' => 0,
            'city' => 'Екатеринбург',
            'street' => 'ул. Вайнера',
            'house' => '36',
            'payment_method' => PaymentMethod::Cash,
            'subtotal' => 2354,
            'discount' => 0,
            'total' => 2354,
        ]);

        return $order->setRelation('items', collect([
            new OrderItem(['name' => 'Кения Волк', 'variant_label' => '250 г', 'grind_label' => 'Для турки', 'price' => 1177, 'quantity' => 2, 'total' => 2354]),
        ]));
    }
}
