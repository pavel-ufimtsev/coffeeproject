<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Services\Checkout\CdekDelivery;
use App\Services\Checkout\CourierRates;
use App\Services\Checkout\DeliveryEstimates;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Help for shoppers: delivery and payment (with a page per city, at the old
 * site's addresses), returns and frequent questions.
 */
class HelpController extends Controller
{
    public function index(): View
    {
        return view('shop.help.index');
    }

    public function delivery(DeliveryEstimates $estimates): View
    {
        $cities = collect(config('shop.delivery_cities'))->map(fn (array $city, string $slug) => [
            ...$city,
            'slug' => $slug,
            'rate' => $estimates->pickup($city['cdek']),
            'points' => $estimates->pointCount($city['cdek']),
        ]);

        return view('shop.help.delivery', [...$this->shipping(), 'cities' => $cities]);
    }

    public function city(DeliveryEstimates $estimates, string $city): View
    {
        $data = config("shop.delivery_cities.{$city}");
        abort_if($data === null, 404);

        return view('shop.help.city', [
            ...$this->shipping(),
            'slug' => $city,
            'city' => $data,
            'rate' => $estimates->pickup($data['cdek']),
            'points' => $estimates->pointCount($data['cdek']),
            'hasCourier' => $data['name'] === config('shop.courier_city'),
            'others' => collect(config('shop.delivery_cities'))->except($city),
            'featured' => Product::active()
                ->where('is_featured', true)
                ->with(['activeVariants', 'mainImage', 'roaster', 'category.parent'])
                ->orderBy('sort')
                ->limit(3)
                ->get(),
        ]);
    }

    public function returns(): View
    {
        return view('shop.help.returns');
    }

    public function faq(): View
    {
        return view('shop.help.faq', $this->shipping());
    }

    /**
     * What the delivery, payment and FAQ pages share: delivery methods with
     * their prices, payment methods and the roast days.
     *
     * @return array<string, mixed>
     */
    private function shipping(): array
    {
        $courier = app(CourierRates::class);
        $cdek = app(CdekDelivery::class);

        $roastDays = Location::where('is_active', true)->pluck('roast_days')->filter()->flatten()->unique()
            ->sortBy(fn (string $day) => Carbon::parse($day)->dayOfWeekIso)
            ->map(fn (string $day) => Carbon::parse($day)->locale('ru')->isoFormat('dd'))
            ->implode(', ');

        return [
            'courier' => $courier->enabled() ? ['price' => $courier->price(), 'free_from' => $courier->freeFrom()] : null,
            'cdek' => $cdek->enabled() ? ['free_from' => $cdek->freeFrom()] : null,
            'payments' => PaymentMethod::enabled(),
            'roastDays' => $roastDays,
        ];
    }
}
