<?php

namespace App\Services\TBank;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Checkout\ConfirmOrder;
use Illuminate\Support\Facades\DB;

/**
 * Paying an order on T-Bank's page: start an attempt, then take in the
 * statuses the bank reports (notification or GetState). An order counts as
 * paid only on a signed CONFIRMED for the full amount.
 */
class CardPayment
{
    public function __construct(
        private readonly TBank $bank,
        private readonly ConfirmOrder $confirm,
    ) {}

    /**
     * Start a payment attempt and return the bank's payment page URL.
     *
     * @throws TBankException
     */
    public function start(Order $order): string
    {
        $attempt = $order->payment_attempts + 1;

        $payment = [
            'Amount' => $this->kopecks($order->total),
            // Must be unique per attempt; a retry gets "-2", "-3"…
            'OrderId' => $attempt === 1 ? $order->number : "{$order->number}-{$attempt}",
            'Description' => mb_substr(__('Order :number, :shop', ['number' => $order->number, 'shop' => config('shop.name')]), 0, 140),
            'Language' => 'ru',
            'NotificationURL' => route('payment.webhook'),
            'SuccessURL' => route('payment.success', ['order' => $order->token]),
            'FailURL' => route('payment.failed', ['order' => $order->token]),
            'DATA' => ['Email' => $order->email, 'Phone' => $order->phone],
        ];

        if (config('services.tbank.receipts')) {
            $payment['Receipt'] = $this->receipt($order);
        }

        $response = $this->bank->init($payment);

        $order->forceFill([
            'payment_id' => (string) $response['PaymentId'],
            'payment_status' => $response['Status'] ?? 'NEW',
            'payment_attempts' => $attempt,
        ])->save();

        return $response['PaymentURL'];
    }

    /**
     * Ask the bank how the latest attempt went, e.g. when the customer comes
     * back before the notification has arrived (or locally, where it never does).
     */
    public function refresh(Order $order): void
    {
        if ($order->payment_id === null || $order->is_paid) {
            return;
        }

        $state = $this->bank->getState($order->payment_id);

        $this->apply($order, (string) $state['Status'], isset($state['Amount']) ? (int) $state['Amount'] : null, $order->payment_id);
    }

    /**
     * Record a status the bank reported for one of the order's attempts.
     */
    public function apply(Order $order, string $status, ?int $amount, string $paymentId): void
    {
        $paidNow = DB::transaction(function () use ($order, $status, $amount, $paymentId) {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

            // A late notification about an older attempt must not overwrite the latest one,
            // unless it says that attempt was paid after all.
            if ($fresh->payment_id !== $paymentId && $status !== 'CONFIRMED') {
                return false;
            }

            // The bank sends AUTHORIZED and CONFIRMED at once; they may arrive in either order.
            if ($fresh->is_paid && ! in_array($status, ['CONFIRMED', 'REFUNDED', 'PARTIAL_REFUNDED', 'REVERSED', 'PARTIAL_REVERSED'], true)) {
                return false;
            }

            $changes = ['payment_status' => $status, 'payment_id' => $paymentId];
            $paidNow = false;

            if ($status === 'CONFIRMED' && $amount === $this->kopecks($fresh->total) && ! $fresh->is_paid) {
                $changes += ['is_paid' => true, 'paid_at' => now()];
                $paidNow = true;
            }

            if (in_array($status, ['REFUNDED', 'REVERSED'], true)) {
                $changes['is_paid'] = false;
            }

            $fresh->forceFill($changes)->save();

            return $paidNow;
        });

        $order->refresh();

        if ($paidNow) {
            $this->confirm->handle($order);
        }
    }

    /**
     * The 54-ФЗ receipt. Item amounts must add up to the payment exactly, so
     * the promo discount is spread over the lines in kopecks, and a line whose
     * discounted total does not divide by its quantity is split in two.
     *
     * @return array<string, mixed>
     */
    public function receipt(Order $order): array
    {
        $config = config('services.tbank');
        $ffd12 = $config['ffd_version'] === '1.2';
        $item = fn (string $name, int $price, int $quantity, string $object) => array_filter([
            'Name' => mb_substr($name, 0, 128),
            'Price' => $price,
            'Quantity' => $quantity,
            'Amount' => $price * $quantity,
            'Tax' => $config['vat'],
            'PaymentMethod' => $config['receipt_payment_method'],
            'PaymentObject' => $object,
            'MeasurementUnit' => $ffd12 ? 'шт' : null,
        ], fn ($value) => $value !== null);

        $lines = $order->items->map(fn (OrderItem $line) => [
            'name' => trim($line->name.' '.$line->meta()),
            'quantity' => $line->quantity,
            'total' => $this->kopecks($line->total),
        ])->values()->all();

        // Line totals after the discount, spread over the lines by their share of the goods.
        $totals = array_column($lines, 'total');
        $goods = array_sum($totals);
        $discount = $this->kopecks($order->discount);
        $left = $discount;

        foreach ($totals as $i => $total) {
            $share = $goods > 0 ? intdiv($discount * $total, $goods) : 0;
            $totals[$i] -= $share;
            $left -= $share;
        }

        // Kopecks lost to rounding come off the most expensive line.
        if ($left > 0 && $totals !== []) {
            $totals[array_keys($totals, max($totals))[0]] -= $left;
        }

        $items = [];

        foreach ($lines as $i => $line) {
            $unit = intdiv($totals[$i], $line['quantity']);
            $rest = $totals[$i] - $unit * $line['quantity'];

            if ($rest === 0) {
                $items[] = $item($line['name'], $unit, $line['quantity'], 'commodity');
            } else {
                if ($line['quantity'] > 1) {
                    $items[] = $item($line['name'], $unit, $line['quantity'] - 1, 'commodity');
                }
                $items[] = $item($line['name'], $unit + $rest, 1, 'commodity');
            }
        }

        if ((float) $order->delivery_price > 0) {
            $items[] = $item(__('Delivery'), $this->kopecks($order->delivery_price), 1, 'service');
        }

        return array_filter([
            'FfdVersion' => $ffd12 ? '1.2' : null,
            'Email' => $order->email,
            'Phone' => $order->phone,
            'Taxation' => $config['taxation'],
            'Items' => $items,
        ], fn ($value) => $value !== null);
    }

    public function kopecks(string|float|int $rubles): int
    {
        return (int) round((float) $rubles * 100);
    }
}
