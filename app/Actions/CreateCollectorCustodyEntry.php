<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CashShift;
use App\Models\CollectorCustodyEntry;
use App\Models\User;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateCollectorCustodyEntry implements Action
{
    public function __construct(
        private GetCollectorCustodyPosition $position,
        private GetCurrencyCatalog $currencies,
    ) {}

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
        $currency = strtoupper($data['currency']);
        if (! in_array($currency, array_column($this->currencies->handle(), 'code'), true)) {
            throw new DomainException('Choose a supported custody currency.');
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

        return DB::transaction(function () use ($actor, $collector, $shift, $data, $tenantId, $manager, $direction, $currency): CollectorCustodyEntry {
            $lockedCollector = User::query()->lockForUpdate()->findOrFail($collector->id);
            if ($manager && $direction === 'debit') {
                $available = (int) ($this->position->handle($lockedCollector)['balances'][$currency] ?? 0);
                if ($available < $data['amount']) {
                    throw new DomainException('The debit exceeds this collector\'s available cash custody.');
                }
            }

            return CollectorCustodyEntry::create([
                'tenant_id' => $tenantId,
                'collector_id' => $lockedCollector->id,
                'cash_shift_id' => $shift?->id,
                'requested_by_id' => $actor->id,
                'reviewed_by_id' => $manager ? $actor->id : null,
                'type' => $data['type'],
                'direction' => $direction,
                'status' => $manager ? 'posted' : 'pending',
                'amount' => $data['amount'],
                'currency' => $currency,
                'description' => trim($data['description']),
                'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'occurred_at' => $data['occurred_at'] ?? now(),
                'reviewed_at' => $manager ? now() : null,
            ]);
        });
    }
}
