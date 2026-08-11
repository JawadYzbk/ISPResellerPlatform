<?php

declare(strict_types=1);

namespace WhishPay;

use JsonException;
use WhishPay\Exceptions\WhishApiException;
use WhishPay\Exceptions\WhishParseException;

final class WhishClient
{
    public const CREATE_PAYMENT_PATH = '/payment/whish';

    public const PAYMENT_STATUS_PATH = '/payment/collect/status';

    public const RATE_PATH = '/payment/whish/rate';

    public const BALANCE_PATH = '/payment/account/balance';

    public function __construct(
        private WhishConfig $config,
        private WhishHttpTransport $transport = new CurlWhishHttpTransport,
    ) {}

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        return PaymentResponse::fromArray($this->call('POST', self::CREATE_PAYMENT_PATH, $request->toArray())['data'] ?? []);
    }

    public function getPaymentStatus(string|int $externalId, string $currency): PaymentStatus
    {
        $currency = strtoupper(trim($currency));
        if (! in_array($currency, ['USD', 'LBP', 'AED'], true)) {
            throw new WhishParseException('Whish supports USD, LBP, and AED payments.');
        }

        $externalId = (string) $externalId;
        if (preg_match('/^[1-9][0-9]*$/', $externalId) !== 1) {
            throw new WhishParseException('Whish external IDs must be positive integers.');
        }

        return PaymentStatus::fromArray($this->call('POST', self::PAYMENT_STATUS_PATH, [
            'currency' => $currency,
            'externalId' => (int) $externalId,
        ])['data'] ?? []);
    }

    /** @return array<string, mixed> */
    public function getRate(string|int $amount, string $currency): array
    {
        return $this->call('POST', self::RATE_PATH, [
            'amount' => str_contains((string) $amount, '.') ? (float) $amount : (int) $amount,
            'currency' => strtoupper($currency),
        ])['data'] ?? [];
    }

    /** @return array<string, mixed> */
    public function getBalance(): array
    {
        return $this->call('GET', self::BALANCE_PATH, null)['data'] ?? [];
    }

    /** @param array<string, mixed>|null $payload @return array<string, mixed> */
    private function call(string $method, string $path, ?array $payload): array
    {
        $response = $this->transport->send($method, $this->config->resolvedBaseUrl().$path, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'channel' => $this->config->channel(),
            'secret' => $this->config->secret(),
            'websiteurl' => $this->config->websiteUrl(),
        ], $payload, $this->config->timeout());

        try {
            $decoded = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new WhishParseException('Whish returned a non-JSON response.', previous: $exception);
        }
        if (! is_array($decoded)) {
            throw new WhishParseException('Whish returned an invalid response.');
        }
        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new WhishApiException(self::message($decoded, 'Whish rejected the request.'), self::code($decoded));
        }
        if (! ($decoded['status'] ?? false)) {
            throw new WhishApiException(self::message($decoded, 'Whish rejected the request.'), self::code($decoded));
        }

        return $decoded;
    }

    /** @param array<string, mixed> $payload */
    private static function message(array $payload, string $fallback): string
    {
        $dialog = $payload['dialog'] ?? null;

        if (is_string($dialog) && trim($dialog) !== '') {
            return $dialog;
        }

        if (is_array($dialog)) {
            foreach (['message', 'title'] as $key) {
                $value = $dialog[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        return $fallback;
    }

    /** @param array<string, mixed> $payload */
    private static function code(array $payload): ?string
    {
        return is_scalar($payload['code'] ?? null) ? (string) $payload['code'] : null;
    }
}
