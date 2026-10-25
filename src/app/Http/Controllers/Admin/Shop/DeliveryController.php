<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Checkout\CourierRates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Delivery prices used at checkout.
 */
class DeliveryController extends Controller
{
    public function edit(CourierRates $courier): Response
    {
        return Inertia::render('admin/delivery/Edit', [
            'courier' => [
                'enabled' => $courier->enabled(),
                'price' => $courier->price(),
                'free_from' => $courier->freeFrom(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'free_from' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        Setting::put([
            'courier.enabled' => (bool) ($data['enabled'] ?? false),
            'courier.price' => (float) $data['price'],
            'courier.free_from' => isset($data['free_from']) ? (float) $data['free_from'] : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery settings saved.')]);

        return back();
    }
}
