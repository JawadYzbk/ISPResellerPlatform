<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\CommissionEntry;
use App\Models\LedgerAccount;
use App\Models\Settlement;
use App\Models\User;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class PayPartnerSettlement implements Action
{
    public function __construct(private PostJournalEntry $journal) {}

    public function handle(Settlement $settlement, ?User $actor = null): Settlement
    {
        if ($settlement->tenant_id !== app(Tenancy::class)->requireId()) {
            throw new DomainException('Settlement must belong to the current tenant.');
        }
        if ($settlement->status === 'paid') {
            return $settlement;
        }
        if ($settlement->status !== 'approved') {
            throw new DomainException('Only approved settlements can be paid.');
        }

        return DB::transaction(function () use ($settlement, $actor): Settlement {
            $locked = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if ($locked->status === 'paid') {
                return $locked;
            }
            if ($locked->status !== 'approved') {
                throw new DomainException('Only approved settlements can be paid.');
            }

            $start = $locked->period_start->copy()->startOfDay();
            $end = $locked->period_end->copy()->endOfDay();
            $currentDue = (int) CommissionEntry::query()
                ->where('partner_id', $locked->partner_id)
                ->where('currency', $locked->currency)
                ->where('status', 'accrued')
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount_minor');
            if ($currentDue !== $locked->due_amount) {
                throw new DomainException('Settlement commission activity changed after approval.');
            }

            if ($locked->due_amount !== 0) {
                $payable = LedgerAccount::query()->where('code', '2210')->firstOrFail();
                $cash = LedgerAccount::query()->where('code', '1000')->firstOrFail();
                $amount = abs($locked->due_amount);
                $lines = $locked->due_amount > 0
                    ? [
                        new JournalLineInput($payable->id, $locked->currency, debitAmount: $amount, partnerId: $locked->partner_id),
                        new JournalLineInput($cash->id, $locked->currency, creditAmount: $amount),
                    ]
                    : [
                        new JournalLineInput($cash->id, $locked->currency, debitAmount: $amount),
                        new JournalLineInput($payable->id, $locked->currency, creditAmount: $amount, partnerId: $locked->partner_id),
                    ];
                $entry = $this->journal->post('Partner settlement payout', $lines, actor: $actor, sourceType: Settlement::class, sourceId: (string) $locked->id);
                $locked->forceFill(['journal_entry_id' => $entry->id])->save();
            }

            CommissionEntry::query()
                ->where('partner_id', $locked->partner_id)
                ->where('currency', $locked->currency)
                ->where('status', 'accrued')
                ->whereBetween('created_at', [$start, $end])
                ->update(['status' => 'settled', 'updated_at' => now()]);
            $locked->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

            return $locked->refresh();
        });
    }
}
