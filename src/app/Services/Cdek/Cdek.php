<?php

namespace App\Services\Cdek;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * CDEK API v2 (https://api-docs.cdek.ru). The site only reads from it:
 * pickup points and delivery prices. Shipments are created in retailCRM.
 *
 * Authorisation is OAuth client credentials; the token lives about an hour
 * and is cached until shortly before it expires.
 */
class Cdek
{
    private const TOKEN_CACHE_KEY = 'cdek.token';

    public function __construct(
        private readonly string $url,
        private readonly ?string $account,
        private readonly ?string $password,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('services.cdek.url'), config('services.cdek.account'), config('services.cdek.password'));
    }

    public function isConfigured(): bool
    {
        return filled($this->account) && filled($this->password);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->send(fn () => $this->client()->get($this->endpoint($path), $query));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function post(string $path, array $body): Response
    {
        return $this->send(fn () => $this->client()->asJson()->post($this->endpoint($path), $body));
    }

    /**
     * One pickup point by its code (e.g. "MSK123"), or null if CDEK has no such point.
     */
    public function pickupPoint(string $code): ?PickupPoint
    {
        $points = (array) $this->get('deliverypoints', ['code' => $code])->json();

        return isset($points[0]) && is_array($points[0]) ? PickupPoint::fromApi($points[0]) : null;
    }

    /**
     * The price and delivery time of one tariff.
     *
     * @param  array{weight: int, length: int, width: int, height: int}  $parcel
     */
    public function rate(int $tariff, int $fromCityCode, int $toCityCode, array $parcel): CdekRate
    {
        $response = $this->post('calculator/tariff', [
            'tariff_code' => $tariff,
            'from_location' => ['code' => $fromCityCode],
            'to_location' => ['code' => $toCityCode],
            'packages' => [$parcel],
        ]);

        $body = (array) $response->json();

        if (! $response->successful() || ! isset($body['total_sum'])) {
            throw new CdekException('CDEK could not price tariff '.$tariff.': '.($body['errors'][0]['message'] ?? "HTTP {$response->status()}"));
        }

        return new CdekRate($tariff, (float) $body['total_sum'], (int) ($body['period_min'] ?? 0), (int) ($body['period_max'] ?? 0));
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()->timeout(15)->withToken($this->token());
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request();

            // A token revoked before its time: get a new one and try once more.
            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);
                $response = $request();
            }

            return $response;
        } catch (ConnectionException $e) {
            throw new CdekException("Could not reach CDEK: {$e->getMessage()}", previous: $e);
        }
    }

    private function token(): string
    {
        if (! $this->isConfigured()) {
            throw new CdekException('CDEK is not configured: set CDEK_ACCOUNT and CDEK_PASSWORD.');
        }

        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached)) {
            return $cached;
        }

        try {
            $response = Http::acceptJson()->asForm()->timeout(15)->post($this->endpoint('oauth/token'), [
                'grant_type' => 'client_credentials',
                'client_id' => $this->account,
                'client_secret' => $this->password,
            ]);
        } catch (ConnectionException $e) {
            throw new CdekException("Could not reach CDEK: {$e->getMessage()}", previous: $e);
        }

        $token = $response->json('access_token');

        if (! is_string($token)) {
            throw new CdekException('CDEK refused the account or password (HTTP '.$response->status().').');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, (int) $response->json('expires_in', 3600) - 120));

        return $token;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->url, '/').'/'.ltrim($path, '/');
    }
}
