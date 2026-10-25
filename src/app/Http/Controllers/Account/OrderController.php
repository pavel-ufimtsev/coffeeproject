<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Show one of the customer's orders.
     */
    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return view('account.orders.show', ['order' => $order->load('items.product.mainImage')]);
    }
}
