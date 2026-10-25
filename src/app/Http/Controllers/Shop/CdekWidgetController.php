<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Cart\Cart;
use App\Services\Cdek\Cdek;
use App\Services\Cdek\CdekException;
use App\Services\Checkout\CdekDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as CdekResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The server side of the CDEK pickup point map (the widget's "servicePath").
 * Unlike CDEK's sample service.php, which forwards anything to the API with
 * our credentials, this answers only the three questions the map asks and
 * fills in the sender city and parcel itself.
 *
 * It also answers the map's address lookups (geocode()), which the widget
 * would otherwise send to Yandex with the public map key.
 */
class CdekWidgetController extends Controller
{
    /** The widget refuses a service whose major version differs from its own. */
    private const SERVICE_VERSION = '4.1.0';

    /** Filters the map may pass when listing pickup points. */
    private const POINT_FILTERS = ['type', 'have_cashless', 'have_cash', 'is_dressing_room', 'allowed_cod', 'is_handout', 'city_code', 'country_code', 'page', 'size', 'lang'];

    /** The visible map area, for points by coordinates. */
    private const BOUNDS = ['latitude_right_top', 'longitude_right_top', 'latitude_left_bottom', 'longitude_left_bottom'];

    public function __invoke(Request $request, Cdek $cdek, CdekDelivery $delivery, Cart $cart): Response|JsonResponse
    {
        try {
            $response = match ($request->input('action')) {
                'offices' => $cdek->get('deliverypoints', $this->filters($request, self::POINT_FILTERS)),
                'byCoordinate' => $cdek->get('deliverypoints/byPolygons', $this->filters($request, [...self::POINT_FILTERS, ...self::BOUNDS])),
                'calculate' => $cdek->post('calculator/tarifflist', [
                    'currency' => 1,
                    'lang' => 'rus',
                    'from_location' => ['code' => (int) config('services.cdek.from_city_code')],
                    'to_location' => $this->destination($request),
                    'packages' => [$delivery->parcel($cart)],
                ]),
                default => null,
            };
        } catch (CdekException $e) {
            report($e);

            return $this->error('CDEK is unavailable', 502);
        }

        if ($response === null) {
            return $this->error('Unknown action', 400);
        }

        return $this->relay($response);
    }

    /**
     * Yandex Geocoder for the map: the city it opens on, the search box and
     * the city under the map's centre. Yandex counts these against a small
     * free daily limit, so answers are kept for a day.
     */
    public function geocode(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'geocode' => ['required', 'string', 'max:200'],
            'results' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $query = [
            'apikey' => config('services.cdek.yandex_geocoder_key'),
            'geocode' => $data['geocode'],
            'format' => 'json',
            'lang' => 'ru_RU',
            'results' => (int) ($data['results'] ?? 10),
        ];

        $key = 'yandex.geocode.'.md5($query['geocode'].'|'.$query['results']);
        $body = Cache::get($key);

        if (! is_string($body)) {
            try {
                $response = Http::acceptJson()->timeout(10)->get('https://geocode-maps.yandex.ru/1.x/', $query);
            } catch (ConnectionException $e) {
                report($e);

                return $this->error('Geocoder is unavailable', 502);
            }

            if (! $response->successful()) {
                report(new \RuntimeException("Yandex Geocoder answered HTTP {$response->status()}: ".mb_substr($response->body(), 0, 200)));

                return $this->error('Geocoder is unavailable', 502);
            }

            $body = $response->body();
            Cache::put($key, $body, now()->addDay());
        }

        return response($body, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $allowed): array
    {
        $filters = collect($request->only($allowed))->filter(fn ($value) => is_scalar($value) && $value !== '')->all();

        // Points customers can collect from, never sender-only offices.
        return [...$filters, 'is_handout' => 'true'];
    }

    /**
     * Where the customer looks for points: a CDEK city code, or an address the map geocoded.
     *
     * @return array<string, mixed>
     */
    private function destination(Request $request): array
    {
        $to = (array) $request->input('to_location', []);

        return array_filter([
            'code' => isset($to['code']) && is_numeric($to['code']) ? (int) $to['code'] : null,
            'address' => isset($to['address']) && is_string($to['address']) ? mb_substr($to['address'], 0, 255) : null,
            'postal_code' => isset($to['postal_code']) && is_string($to['postal_code']) ? mb_substr($to['postal_code'], 0, 20) : null,
            'country_code' => isset($to['country_code']) && is_string($to['country_code']) ? mb_substr($to['country_code'], 0, 2) : null,
        ]);
    }

    /**
     * CDEK's answer as is, with the paging headers (X-Total-Elements…) the map reads.
     */
    private function relay(CdekResponse $response): Response
    {
        $headers = collect($response->headers())
            ->filter(fn ($value, string $name) => str_starts_with(strtolower($name), 'x-total') || str_starts_with(strtolower($name), 'x-current'))
            ->map(fn (array $values) => $values[0])
            ->all();

        return response($response->body(), $response->status(), [
            ...$headers,
            'Content-Type' => 'application/json',
            'X-Service-Version' => self::SERVICE_VERSION,
        ]);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status, ['X-Service-Version' => self::SERVICE_VERSION]);
    }
}
