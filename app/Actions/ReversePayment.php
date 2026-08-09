<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Enums\PaymentStatus;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReversePayment implements Action
{
    public function __construct(private PostJournalEntry $journal) {}

    public function handle(Payment $payment, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::Posted) {
                throw new DomainException('Only posted payments can be reversed.');
            }
            $cash = LedgerAccount::query()->where('code', '1000')->firstOrFail();
            $receivable = LedgerAccount::query()->where('code', '1100')->firstOrFail();
            $this->journal->post(
                'Reverse payment '.$payment->number,
                [
                    new JournalLineInput($cash->id, $payment->currency, creditAmount: $payment->amount),
                    new JournalLineInput($receivable->id, $payment->currency, debitAmount: $payment->amount, customerId: $payment->customer_id),
                ],
                actor: $actor,
                sourceType: Payment::class.':reversal',
                sourceId: (string) $payment->id,
            );
            $payment->forceFill(['status' => PaymentStatus::Reversed, 'reversed_at' => now()])->save();

            return $payment->refresh();
        });
    }
}
