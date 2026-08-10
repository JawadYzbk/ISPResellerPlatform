<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;

final readonly class RecordCollectorPaymentBatch implements Action
{
    public function __construct(private RecordPayment $recordPayment) {}

    /** @param list<array<string, mixed>> $items @return array{results: list<array<string, mixed>>} */
    public function handle(array $items, User $actor): array
    {
        $results = [];
        foreach ($items as $index => $item) {
            try {
                foreach (['customer_id', 'amount', 'currency', 'method', 'idempotency_key'] as $required) {
                    if (! array_key_exists($required, $item)) {
                        throw new \InvalidArgumentException("Missing {$required}.");
                    }
                }
                if (! is_string($item['customer_id']) || ! is_int($item['amount']) || $item['amount'] < 1 || ! is_string($item['currency']) || ! is_string($item['method']) || ! is_string($item['idempotency_key'])) {
                    throw new \InvalidArgumentException('The payment payload is malformed.');
                }
                if (($item['fx_override'] ?? false) && (! is_int($item['fx_rate_numerator'] ?? null) || ! is_int($item['fx_rate_denominator'] ?? null) || ! is_string($item['fx_override_reason'] ?? null))) {
                    throw new \InvalidArgumentException('The FX override payload is malformed.');
                }
                $customer = Customer::query()->where('public_id', $item['customer_id'])->firstOrFail();
                $invoice = isset($item['invoice_id']) ? Invoice::query()->where('public_id', $item['invoice_id'])->firstOrFail() : null;
                $payment = $this->recordPayment->handle(
                    $customer,
                    $item['amount'],
                    strtoupper($item['currency']),
                    $item['method'],
                    $item['idempotency_key'],
                    $invoice,
                    $actor,
                    null,
                    ($item['fx_override'] ?? false) ? $item['fx_rate_numerator'] : null,
                    ($item['fx_override'] ?? false) ? $item['fx_rate_denominator'] : null,
                    ($item['fx_override'] ?? false) ? $item['fx_override_reason'] : null,
                    isset($item['reference']) && is_string($item['reference']) ? $item['reference'] : null,
                );
                $results[] = ['index' => $index, 'status' => 'ok', 'payment_id' => $payment->public_id];
            } catch (\Throwable $exception) {
                $results[] = ['index' => $index, 'status' => 'error', 'error' => $exception->getMessage()];
            }
        }

        return ['results' => $results];
    }
}
