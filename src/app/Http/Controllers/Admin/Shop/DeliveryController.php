<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Cdek\Cdek;
use App\Services\Checkout\CdekDelivery;
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
    public function edit(CourierRates $courier, CdekDelivery $cdek, Cdek $api): Response
    {
        return Inertia::render('admin/delivery/Edit', [
            'courier' => [
                'enabled' => $courier->enabled(),
                'price' => $courier->price(),
                'free_from' => $courier->freeFrom(),
            ],
            'cdek' => [
                'enabled' => (bool) Setting::get('cdek.enabled', true),
                'free_from' => $cdek->freeFrom(),
                ...$cdek->box(),
                // What still keeps CDEK off the checkout, for the hint under the form.
                'missing' => array_values(array_filter([
                    $api->isConfigured() ? null : 'CDEK_ACCOUNT, CDEK_PASSWORD',
                    filled(config('services.cdek.yandex_maps_key')) ? null : 'CDEK_YANDEX_MAPS_KEY',
                    filled(config('services.cdek.yandex_geocoder_key')) ? null : 'CDEK_YANDEX_GEOCODER_KEY',
                    PaymentMethod::TBank->isEnabled() ? null : 'T-Bank',
                ])),
            ],
        ]);
    }

    public function updateCdek(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'free_from' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'length' => ['required', 'integer', 'min:1', 'max:150'],
            'width' => ['required', 'integer', 'min:1', 'max:150'],
            'height' => ['required', 'integer', 'min:1', 'max:150'],
        ]);

        Setting::put([
            'cdek.enabled' => (bool) ($data['enabled'] ?? false),
            'cdek.free_from' => isset($data['free_from']) ? (float) $data['free_from'] : null,
            'cdek.box' => ['length' => (int) $data['length'], 'width' => (int) $data['width'], 'height' => (int) $data['height']],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery settings saved.')]);

        return back();
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
