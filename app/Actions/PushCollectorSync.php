<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Payment;
use App\Models\User;

final readonly class PushCollectorSync implements Action
{
    public function __construct(private RecordCollectorPaymentBatch $batch) {}

    /** @param list<array<string, mixed>> $items @return array{results: list<array<string, mixed>>} */
    public function handle(array $items, User $actor): array
    {
        $results = [];
        foreach ($items as $index => $item) {
            $normalized = $item;
            if (isset($normalized['customer_uuid']) && ! isset($normalized['customer_id'])) {
                $normalized['customer_id'] = $normalized['customer_uuid'];
            }

            $key = is_string($normalized['idempotency_key'] ?? null) ? $normalized['idempotency_key'] : null;
            $existing = $key === null ? null : Payment::query()->where('idempotency_key', $key)->first();
            $result = $this->batch->handle([$normalized], $actor)['results'][0] ?? ['status' => 'error', 'error' => 'The payment payload is malformed.'];

            if ($result['status'] === 'ok') {
                $results[] = [
                    'index' => $index,
                    'status' => $existing instanceof Payment ? 'replayed' : 'created',
                    'payment_id' => $result['payment_id'],
                ];
            } else {
                $results[] = ['index' => $index, 'status' => 'rejected', 'reason' => $result['error'] ?? 'The payment was rejected.'];
            }
        }

        return ['results' => $results];
    }
}
