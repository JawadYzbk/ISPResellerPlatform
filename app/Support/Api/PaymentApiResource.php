<?php

namespace App\Support\Api;

use App\Models\Payment;
use Carbon\CarbonImmutable;

final readonly class PaymentApiResource
{
    /** @return array<string, mixed> */
    public function make(Payment $payment): array
    {
        $payment->loadMissing(['customer', 'invoice', 'cashShift', 'actor', 'allocations.invoice']);

        return [
            'id' => $payment->public_id,
            'number' => $payment->number,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'ledger_amount' => $payment->ledger_amount ?? $payment->amount,
            'ledger_currency' => $payment->ledger_currency ?? $payment->currency,
            'base_amount' => $payment->base_amount ?? $payment->amount,
            'base_currency' => $payment->metadata['base_currency'] ?? $payment->ledger_currency ?? $payment->currency,
            'fx_rate_numerator' => $payment->fx_rate_numerator,
            'fx_rate_denominator' => $payment->fx_rate_denominator,
            'fx_rate_overridden' => $payment->fx_rate_overridden,
            'fx_override_reason' => $payment->fx_override_reason,
            'fx_rounding_mode' => $payment->metadata['base_fx_snapshot']['rounding_mode'] ?? null,
            'fx_rate_source' => $payment->metadata['base_fx_snapshot']['rate_source'] ?? $payment->metadata['base_fx_source'] ?? null,
            'fx_rate_effective_from' => $payment->metadata['base_fx_snapshot']['effective_from'] ?? null,
            'reference' => $payment->reference,
            'method' => $payment->method,
            'received_at' => $this->isoDate($payment->received_at),
            'reversed_at' => $this->isoDate($payment->reversed_at),
            'collector' => $payment->actor?->name,
            'cash_shift_id' => $payment->cashShift?->public_id,
            'customer' => $payment->customer === null ? null : [
                'id' => $payment->customer->public_id,
                'code' => $payment->customer->code,
                'name' => $payment->customer->full_name,
            ],
            'invoice' => $payment->invoice === null ? null : [
                'id' => $payment->invoice->public_id,
                'number' => $payment->invoice->number,
            ],
            'allocations' => $payment->allocations->map(fn ($allocation): array => [
                'id' => $allocation->id,
                'amount' => $allocation->amount,
                'currency' => $allocation->currency,
                'invoice' => [
                    'id' => $allocation->invoice->public_id,
                    'number' => $allocation->invoice->number,
                ],
            ])->values()->all(),
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
