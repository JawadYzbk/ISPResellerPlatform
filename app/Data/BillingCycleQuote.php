<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class BillingCycleQuote
{
    public function __construct(
        public int $anchorDay,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $billableDays,
        public int $cycleDays,
        public int $fullAmount,
        public int $proratedAmount,
        public string $currency,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'anchor_day' => $this->anchorDay,
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'billable_days' => $this->billableDays,
            'cycle_days' => $this->cycleDays,
            'full_amount' => $this->fullAmount,
            'prorated_amount' => $this->proratedAmount,
            'currency' => $this->currency,
        ];
    }
}
