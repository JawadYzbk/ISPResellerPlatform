<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CashShift;
use App\Models\CollectorCustodyEntry;
use App\Models\User;
use App\Support\Tenancy;
use DomainException;

final readonly class CreateCollectorCustodyEntry implements Action
{
    /** @param array{type: string, direction?: string, amount: int, currency: string, description: string, reference?: string|null, occurred_at?: string|null} $data */
    public function handle(User $actor, User $collector, ?CashShift $shift, array $data): CollectorCustodyEntry
    {
        $tenantId = app(Tenancy::class)->requireId();
        $manager = $actor->can('reports.operations');
        if ((int) $collector->tenant_id !== $tenantId || $collector->role !== 'collector') {
            throw new DomainException('Choose a collector from this workspace.');
        }
        if (! $manager && (int) $actor->id !== (int) $collector->id) {
            throw new DomainException('You can only submit custody entries for yourself.');
        }
        if ($shift !== null && ((int) $shift->tenant_id !== $tenantId || (int) $shift->user_id !== (int) $collector->id)) {
            throw new DomainException('The selected cash shift does not belong to this collector.');
        }
        if (! in_array($data['type'], CollectorCustodyEntry::TYPES, true) || $data['amount'] <= 0) {
            throw new DomainException('Provide a valid custody entry and positive amount.');
        }

        $direction = $data['type'] === 'adjustment'
            ? ($data['direction'] ?? '')
            : ($data['type'] === 'advance' ? 'credit' : 'debit');
        if (! in_array($direction, CollectorCustodyEntry::DIRECTIONS, true)) {
            throw new DomainException('Choose whether the adjustment adds or removes custody.');
        }
        if (! $manager && in_array($data['type'], ['advance', 'adjustment'], true)) {
            throw new DomainException('Only a manager can record advances or adjustments.');
        }

        return CollectorCustodyEntry::create([
            'tenant_id' => $tenantId,
            'collector_id' => $collector->id,
            'cash_shift_id' => $shift?->id,
            'requested_by_id' => $actor->id,
            'reviewed_by_id' => $manager ? $actor->id : null,
            'type' => $data['type'],
            'direction' => $direction,
            'status' => $manager ? 'posted' : 'pending',
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency']),
            'description' => trim($data['description']),
            'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'reviewed_at' => $manager ? now() : null,
        ]);
    }
}
