<?php

namespace App\Services\RetailCrm;

use RetailCrm\ApiClient;
use RetailCrm\Client\ApiVersion5;
use RetailCrm\Exception\CurlException;
use RetailCrm\Response\ApiResponse;

/**
 * Thin wrapper around the official retailCRM SDK (API v5).
 *
 * The rest of the app talks to retailCRM through this class, so failed
 * responses always surface as RetailCrmException and the SDK can be
 * upgraded (v6 does not support Symfony 8 yet) without touching callers.
 */
class RetailCrm
{
    private ?ApiVersion5 $api = null;

    public function __construct(
        private readonly ?string $url,
        private readonly ?string $apiKey,
        private readonly ?string $site = null,
    ) {}

    /**
     * Determine whether the URL and API key are set.
     */
    public function isConfigured(): bool
    {
        return filled($this->url) && filled($this->apiKey);
    }

    /**
     * Get the store code orders are created in.
     */
    public function site(): ?string
    {
        return $this->site;
    }

    /**
     * Get the account URL.
     */
    public function url(): ?string
    {
        return $this->url;
    }

    /**
     * Get the API methods and stores the API key has access to.
     *
     * @return array{credentials?: list<string>, siteAccess?: string, sitesAvailable?: list<string>}
     */
    public function credentials(): array
    {
        return $this->call(fn (ApiVersion5 $api) => $api->credentials());
    }

    /**
     * Get the stores ("магазины") set up in the account, keyed by code.
     *
     * @return array<string, array<string, mixed>>
     */
    public function sites(): array
    {
        return $this->call(fn (ApiVersion5 $api) => $api->sitesList())['sites'] ?? [];
    }

    /**
     * Get the delivery types, keyed by code.
     *
     * @return array<string, array<string, mixed>>
     */
    public function deliveryTypes(): array
    {
        return $this->call(fn (ApiVersion5 $api) => $api->deliveryTypesList())['deliveryTypes'] ?? [];
    }

    /**
     * Get the payment types, keyed by code.
     *
     * @return array<string, array<string, mixed>>
     */
    public function paymentTypes(): array
    {
        return $this->call(fn (ApiVersion5 $api) => $api->paymentTypesList())['paymentTypes'] ?? [];
    }

    /**
     * Get the low-level SDK client for calls not wrapped here yet.
     */
    public function api(): ApiVersion5
    {
        if (! $this->isConfigured()) {
            throw RetailCrmException::notConfigured();
        }

        return $this->api ??= (new ApiClient($this->url, $this->apiKey, ApiClient::V5, $this->site))->request;
    }

    /**
     * Run an SDK call and return its decoded body, or throw on failure.
     *
     * @param  callable(ApiVersion5): ApiResponse  $request
     * @return array<string, mixed>
     */
    private function call(callable $request): array
    {
        try {
            $response = $request($this->api());
        } catch (CurlException $e) {
            throw new RetailCrmException("Could not reach retailCRM: {$e->getMessage()}", previous: $e);
        }

        // getResponse() is null when the body is empty, despite its docblock.
        $body = (array) $response->getResponse();

        if (! $response->isSuccessful()) {
            $message = $body['errorMsg'] ?? "HTTP {$response->getStatusCode()}";
            $errors = isset($body['errors']) ? ' '.json_encode($body['errors'], JSON_UNESCAPED_UNICODE) : '';

            throw new RetailCrmException("retailCRM request failed: {$message}{$errors}");
        }

        return $body;
    }
}
