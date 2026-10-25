<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Jobs\PushOrderToRetailCrm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RetailCrm\RetailCrm;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OrderController extends Controller
{
    /**
     * List orders, newest first, optionally filtered by a search term and status.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ]);

        $orders = Order::query()
            ->withSum('items as item_count', 'quantity')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $digits = preg_replace('/\D/', '', $search);

                $query->where(fn ($query) => $query
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->when(strlen($digits) >= 4, fn ($query) => $query->orWhere('phone', 'like', '%'.substr($digits, -10).'%')));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'created_at' => $order->created_at->format('d.m.Y H:i'),
                'customer' => $order->customerName(),
                'phone' => $order->formattedPhone(),
                'item_count' => (int) $order->item_count,
                'total' => Money::rub($order->total),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'in_retailcrm' => $order->retailcrm_id !== null,
                'retailcrm_failed' => $order->retailcrm_error !== null,
            ]);

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'statuses' => OrderStatus::options(),
        ]);
    }

    /**
     * Show an order.
     */
    public function show(Order $order, RetailCrm $crm): Response
    {
        $order->load('items', 'user');

        return Inertia::render('admin/orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'token' => $order->token,
                'created_at' => $order->created_at->format('d.m.Y H:i'),
                'status' => $order->status->value,
                'is_paid' => $order->is_paid,
                'customer' => $order->customerName(),
                'phone' => $order->formattedPhone(),
                'phone_link' => $order->phone,
                'email' => $order->email,
                'user' => $order->user?->only(['id', 'name']),
                'delivery' => $order->delivery_method->label(),
                'address' => $order->destinationLine(),
                'access' => $order->pickup_point_code ? $order->delivery_days : $order->accessLine(),
                'courier_note' => $order->courier_note,
                'payment' => $order->payment_method->label(),
                'payment_status' => $order->paymentStatusLabel(),
                'payment_id' => $order->payment_id,
                'paid_at' => $order->paid_at?->format('d.m.Y H:i'),
                'comment' => $order->comment,
                'promo_code' => $order->promo_code,
                'subtotal' => Money::rub($order->subtotal),
                'discount' => (float) $order->discount > 0 ? '−'.Money::rub($order->discount) : null,
                'delivery_price' => (float) $order->delivery_price > 0 ? Money::rub($order->delivery_price) : __('Free'),
                'total' => Money::rub($order->total),
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'meta' => $item->meta(),
                    'price' => Money::rub($item->price),
                    'quantity' => $item->quantity,
                    'total' => Money::rub($item->total),
                ]),
                'retailcrm_id' => $order->retailcrm_id,
                'retailcrm_url' => $order->retailcrm_id && $crm->url() ? rtrim($crm->url(), '/')."/orders/{$order->retailcrm_id}/edit" : null,
                'retailcrm_error' => $order->retailcrm_error,
            ],
            'statuses' => OrderStatus::options(),
            'retailcrmEnabled' => $crm->isConfigured(),
        ]);
    }

    /**
     * Change the status or the paid flag.
     */
    public function update(Request $request, Order $order): RedirectResponse
    {
        $order->update($request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'is_paid' => ['boolean'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order saved.')]);

        return back();
    }

    /**
     * Send the order to retailCRM now, e.g. after an earlier attempt failed.
     */
    public function pushToRetailCrm(Order $order, RetailCrm $crm): RedirectResponse
    {
        try {
            (new PushOrderToRetailCrm($order))->handle($crm);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Order sent to retailCRM.')]);
        } catch (Throwable $e) {
            $order->forceFill(['retailcrm_error' => $e->getMessage()])->save();
            Inertia::flash('toast', ['type' => 'error', 'message' => __('retailCRM refused the order: :error', ['error' => $e->getMessage()])]);
        }

        return back();
    }
}
