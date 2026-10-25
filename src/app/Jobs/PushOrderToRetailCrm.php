<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RetailCrm\RetailCrm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Creates the order in retailCRM, where managers process it. The shop's
 * number is sent as externalId, so retailCRM refuses a duplicate if the job
 * runs twice. The outcome is kept on the order for the admin to see.
 */
class PushOrderToRetailCrm implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public Order $order) {}

    public function handle(RetailCrm $crm): void
    {
        if ($this->order->retailcrm_id !== null) {
            return;
        }

        $payload = $this->payload();

        if ($customerId = $crm->findCustomerId($this->order->email)) {
            $payload['customer'] = ['id' => $customerId];
        }

        $id = $crm->createOrder($payload);

        $this->order->forceFill(['retailcrm_id' => (string) $id, 'retailcrm_pushed_at' => now(), 'retailcrm_error' => null])->save();
    }

    public function failed(Throwable $e): void
    {
        $this->order->forceFill(['retailcrm_error' => $e->getMessage()])->save();
    }

    /**
     * The order in retailCRM's API v5 format.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $order = $this->order->loadMissing('items');

        return array_filter([
            'externalId' => $order->number,
            'number' => $order->number,
            'orderMethod' => 'shopping-cart',
            'firstName' => $order->first_name,
            'lastName' => $order->last_name,
            'phone' => $order->phone,
            'email' => $order->email,
            'customerComment' => $order->comment,
            'managerComment' => $order->promo_code ? "Промокод {$order->promo_code}" : null,
            'discountManualAmount' => (float) $order->discount ?: null,
            'items' => $order->items->map(fn (OrderItem $item) => array_filter([
                'offer' => $item->retailcrm_offer_id ? ['id' => $item->retailcrm_offer_id] : null,
                'productName' => trim("{$item->name} {$item->variant_label}"),
                'initialPrice' => (float) $item->price,
                'quantity' => $item->quantity,
                'properties' => $item->grind_label ? [['code' => 'grind', 'name' => 'Помол', 'value' => $item->grind_label]] : null,
            ]))->all(),
            'delivery' => [
                'code' => $order->delivery_method->retailCrmCode(),
                'cost' => (float) $order->delivery_price,
                'address' => array_filter([
                    'city' => $order->city,
                    'street' => $order->street,
                    'building' => $order->house,
                    'flat' => $order->apartment,
                    // retailCRM takes only numbers here; anything else goes in the notes.
                    'block' => ctype_digit((string) $order->entrance) ? (int) $order->entrance : null,
                    'floor' => ctype_digit((string) $order->floor) ? (int) $order->floor : null,
                    'intercomCode' => $order->intercom,
                    'notes' => collect([$order->accessLine(), $order->courier_note])->filter()->implode('. ') ?: null,
                    'text' => $order->addressLine(),
                ]),
            ],
            'payments' => [array_filter([
                'type' => $order->payment_method->retailCrmCode(),
                // Card orders reach retailCRM only once the bank has confirmed them.
                'status' => $order->is_paid ? 'paid' : null,
                'amount' => $order->is_paid ? (float) $order->total : null,
                'externalId' => $order->payment_id,
            ])],
        ], fn ($value) => $value !== null);
    }
}
