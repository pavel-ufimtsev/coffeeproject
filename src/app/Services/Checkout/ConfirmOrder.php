<?php

namespace App\Services\Checkout;

use App\Jobs\PushOrderToRetailCrm;
use App\Mail\OrderPlaced;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The order is real: email the confirmation and hand it to retailCRM. Cash
 * orders get here as soon as they are placed, card orders once the bank
 * confirms the payment. Runs once per order however often it is called
 * (the bank's notification and the customer's return can arrive together).
 */
class ConfirmOrder
{
    public function handle(Order $order): void
    {
        $claimed = DB::transaction(function () use ($order) {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($fresh->confirmed_at !== null) {
                return false;
            }

            $fresh->forceFill(['confirmed_at' => now()])->save();

            return true;
        });

        if (! $claimed) {
            return;
        }

        $order->refresh();

        Mail::to($order->email)->queue(new OrderPlaced($order));

        if (config('services.retailcrm.push_orders')) {
            PushOrderToRetailCrm::dispatch($order);
        }
    }
}
