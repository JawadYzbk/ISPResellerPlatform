<?php

declare(strict_types=1);

namespace WhishPay;

use WhishPay\Exceptions\WhishParseException;

final readonly class PaymentStatus
{
    public function __construct(
        public string $collectStatus,
        public ?string $amount,
        public ?string $currency,
        public ?string $transactionId,
        /** @var array<string, mixed> */
        public array $additionalData,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = strtolower((string) ($data['collectStatus'] ?? ''));
        if (! in_array($status, ['success', 'failed', 'pending'], true)) {
            throw new WhishParseException('Whish returned an unsupported collection status.');
        }

        $currency = isset($data['currency']) ? strtoupper((string) $data['currency']) : null;
        if ($currency !== null && ! in_array($currency, ['USD', 'LBP', 'AED'], true)) {
            throw new WhishParseException('Whish returned an unsupported payment currency.');
        }

        return new self(
            collectStatus: $status,
            amount: self::amount($data['amount'] ?? null),
            currency: $currency,
            transactionId: is_scalar($data['transactionId'] ?? null) ? (string) $data['transactionId'] : null,
            additionalData: is_array($data['additionalData'] ?? null) ? $data['additionalData'] : [],
        );
    }

    private static function amount(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_string($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
        }

        throw new WhishParseException('Whish returned an invalid payment amount.');
    }
}
