<?php

namespace App\Domain\Payments;

final readonly class PaymentIntentResult
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $status,
        public int $amount,
        public string $currency,
        public array $payload = [],
    ) {}
}
