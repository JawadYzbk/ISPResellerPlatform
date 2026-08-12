<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\LedgerAccount;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateSupplierBill implements Action
{
    public function __construct(private PostJournalEntry $journal) {}

    /** @param array<string, mixed> $data */
    public function handle(Supplier $supplier, array $data, ?User $actor = null): SupplierBill
    {
        return DB::transaction(function () use ($supplier, $data, $actor): SupplierBill {
            $bill = $supplier->bills()->create([
                'reference' => trim((string) $data['reference']),
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'amount' => $data['amount'],
                'currency' => strtoupper((string) $data['currency']),
                'status' => 'open',
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            ]);
            $cost = LedgerAccount::query()->where('code', '5200')->firstOrFail();
            $payable = LedgerAccount::query()->where('code', '2220')->firstOrFail();
            $entry = $this->journal->post(
                'Supplier bill '.$bill->reference,
                [
                    new JournalLineInput($cost->id, $bill->currency, debitAmount: (int) $bill->amount, memo: $bill->reference),
                    new JournalLineInput($payable->id, $bill->currency, creditAmount: (int) $bill->amount, memo: $supplier->code),
                ],
                actor: $actor,
                sourceType: SupplierBill::class,
                sourceId: (string) $bill->id,
            );
            $bill->forceFill(['journal_entry_id' => $entry->id])->save();

            return $bill->refresh();
        });
    }
}
