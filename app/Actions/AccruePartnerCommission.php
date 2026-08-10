<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\CommissionEntry;
use App\Models\LedgerAccount;
use App\Models\Partner;
use App\Models\PriceBookItem;
use App\Models\User;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AccruePartnerCommission implements Action
{
    public function __construct(
        private CalculatePartnerCommission $calculator,
        private PostJournalEntry $journal,
    ) {}

    public function handle(Partner $partner, string $sourceType, string $sourceId, PriceBookItem $item, ?User $actor = null): CommissionEntry
    {
        if ($partner->tenant_id !== $item->tenant_id || $partner->tenant_id !== app(Tenancy::class)->requireId()) {
            throw new DomainException('Partner and price book item must belong to the current tenant.');
        }

        $existing = CommissionEntry::query()
            ->where('partner_id', $partner->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
        if ($existing instanceof CommissionEntry) {
            return $existing;
        }

        return DB::transaction(function () use ($partner, $sourceType, $sourceId, $item, $actor): CommissionEntry {
            $existing = CommissionEntry::query()
                ->where('partner_id', $partner->id)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof CommissionEntry) {
                return $existing;
            }

            $amount = $this->calculator->handle($item);
            $rule = $item->commissionRule()->first();
            $ruleVersion = $rule?->version ?? 0;
            $entry = CommissionEntry::create([
                'partner_id' => $partner->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'rule_version' => $ruleVersion,
                'amount_minor' => $amount,
                'currency' => $item->currency,
                'status' => 'accrued',
            ]);

            if ($amount === 0) {
                return $entry;
            }

            $expense = LedgerAccount::query()->where('code', '5100')->firstOrFail();
            $payable = LedgerAccount::query()->where('code', '2210')->firstOrFail();
            $absolute = abs($amount);
            $lines = $amount > 0
                ? [
                    new JournalLineInput($expense->id, $item->currency, debitAmount: $absolute),
                    new JournalLineInput($payable->id, $item->currency, creditAmount: $absolute, partnerId: $partner->id),
                ]
                : [
                    new JournalLineInput($payable->id, $item->currency, debitAmount: $absolute, partnerId: $partner->id),
                    new JournalLineInput($expense->id, $item->currency, creditAmount: $absolute),
                ];
            $journalEntry = $this->journal->post('Partner commission accrual', $lines, actor: $actor, sourceType: CommissionEntry::class, sourceId: (string) $entry->id);
            $entry->forceFill(['journal_entry_id' => $journalEntry->id])->save();

            return $entry->refresh();
        });
    }
}
