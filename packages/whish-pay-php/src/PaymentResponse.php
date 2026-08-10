<?php

declare(strict_types=1);

namespace WhishPay;

use WhishPay\Exceptions\WhishParseException;

final readonly class PaymentResponse
{
    public function __construct(
        public bool $success,
        public string $collectUrl,
        public ?string $code,
        public ?string $dialog,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $collectUrl = $data['collectUrl'] ?? $data['whishUrl'] ?? null;
        if (! is_string($collectUrl) || filter_var($collectUrl, FILTER_VALIDATE_URL) === false) {
            throw new WhishParseException('Whish did not return a valid collection URL.');
        }

        return new self(
            success: true,
            collectUrl: $collectUrl,
            code: is_scalar($data['code'] ?? null) ? (string) $data['code'] : null,
            dialog: is_string($data['dialog'] ?? null) ? $data['dialog'] : null,
        );
    }
}
