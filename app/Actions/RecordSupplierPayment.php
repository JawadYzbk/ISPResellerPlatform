<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\LedgerAccount;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RecordSupplierPayment implements Action
{
    public function __construct(private PostJournalEntry $journal) {}

    /** @param array<string, mixed> $data */
    public function handle(SupplierBill $bill, User $actor, array $data): SupplierPayment
    {
        return DB::transaction(function () use ($bill, $actor, $data): SupplierPayment {
            $lockedBill = SupplierBill::query()->lockForUpdate()->findOrFail($bill->id);
            $paid = (int) SupplierPayment::query()->where('supplier_bill_id', $lockedBill->id)->sum('amount');
            $amount = (int) $data['amount'];
            $remaining = (int) $lockedBill->amount - $paid;

            if ($amount > $remaining) {
                throw new InvalidArgumentException('The supplier payment exceeds the bill balance.');
            }

            $payment = $lockedBill->payments()->create([
                'amount' => $amount,
                'currency' => $lockedBill->currency,
                'paid_at' => $data['paid_at'],
                'method' => trim((string) $data['method']),
                'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'actor_id' => $actor->id,
            ]);
            $payable = LedgerAccount::query()->where('code', '2220')->firstOrFail();
            $cash = LedgerAccount::query()->where('code', '1000')->firstOrFail();
            $entry = $this->journal->post(
                'Supplier payment '.$lockedBill->reference,
                [
                    new JournalLineInput($payable->id, $lockedBill->currency, debitAmount: $amount, memo: $lockedBill->reference),
                    new JournalLineInput($cash->id, $lockedBill->currency, creditAmount: $amount, memo: $payment->reference),
                ],
                occurredAt: CarbonImmutable::parse((string) $payment->paid_at),
                actor: $actor,
                sourceType: SupplierPayment::class,
                sourceId: (string) $payment->id,
            );
            $payment->forceFill(['journal_entry_id' => $entry->id])->save();
            $lockedBill->forceFill(['status' => $amount + $paid >= (int) $lockedBill->amount ? 'paid' : 'open'])->save();

            return $payment;
        });
    }
}
