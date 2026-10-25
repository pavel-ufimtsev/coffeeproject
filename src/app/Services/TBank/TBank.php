<?php

namespace App\Services\TBank;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * T-Bank internet acquiring, API v2 (https://developer.tbank.ru/eacq).
 *
 * TBANK_MODE picks the test or live terminal; both talk to the same address.
 * Every request and every notification is signed with the terminal password,
 * see token().
 */
class TBank
{
    /**
     * @param  array{terminal_key: string|null, password: string|null}  $terminal
     */
    public function __construct(
        private readonly string $url,
        private readonly string $mode,
        private readonly array $terminal,
    ) {}

    public static function fromConfig(): self
    {
        $mode = config('services.tbank.mode') === 'live' ? 'live' : 'test';

        return new self(config('services.tbank.url'), $mode, config("services.tbank.{$mode}"));
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function isConfigured(): bool
    {
        return filled($this->terminal['terminal_key'] ?? null) && filled($this->terminal['password'] ?? null);
    }

    /**
     * Start a payment. Returns the response with PaymentId and PaymentURL.
     *
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    public function init(array $payment): array
    {
        return $this->call('Init', $payment);
    }

    /**
     * Get a payment's current status.
     *
     * @return array<string, mixed>
     */
    public function getState(string $paymentId): array
    {
        return $this->call('GetState', ['PaymentId' => $paymentId]);
    }

    /**
     * Whether a notification really comes from T-Bank for our terminal.
     *
     * @param  array<string, mixed>  $notification
     */
    public function verify(array $notification): bool
    {
        if (! $this->isConfigured() || ! is_string($notification['Token'] ?? null)) {
            return false;
        }

        return ($notification['TerminalKey'] ?? null) === $this->terminal['terminal_key']
            && hash_equals($this->token($notification), $notification['Token']);
    }

    /**
     * The request signature: the top-level values plus the password, sorted
     * by key, joined and hashed with SHA-256. Nested objects (DATA, Receipt)
     * and the Token itself are left out; booleans count as "true"/"false".
     *
     * @param  array<string, mixed>  $params
     */
    public function token(array $params): string
    {
        $values = collect([...$params, 'Password' => $this->terminal['password']])
            ->except('Token')
            ->reject(fn ($value) => is_array($value) || is_object($value) || $value === null)
            ->map(fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)
            ->sortKeys(SORT_STRING)
            ->implode('');

        return hash('sha256', $values);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $method, array $params): array
    {
        if (! $this->isConfigured()) {
            throw TBankException::notConfigured($this->mode);
        }

        $params = ['TerminalKey' => $this->terminal['terminal_key'], ...$params];
        $params['Token'] = $this->token($params);

        try {
            $response = Http::acceptJson()->asJson()->timeout(20)->post(rtrim($this->url, '/')."/{$method}", $params);
        } catch (ConnectionException $e) {
            throw new TBankException("Could not reach T-Bank: {$e->getMessage()}", previous: $e);
        }

        $body = (array) $response->json();

        if (! $response->successful() || ($body['Success'] ?? false) !== true) {
            $message = trim(($body['Message'] ?? "HTTP {$response->status()}").' '.($body['Details'] ?? ''));

            throw new TBankException("T-Bank {$method} failed (code ".($body['ErrorCode'] ?? '?')."): {$message}");
        }

        return $body;
    }
}
