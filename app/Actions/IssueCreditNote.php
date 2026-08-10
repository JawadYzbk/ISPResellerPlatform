<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class IssueCreditNote implements Action
{
    public function __construct(private DocumentNumberGenerator $numbers, private PostJournalEntry $journal) {}

    public function handle(Invoice $invoice, int $amount, string $reason, User $actor): CreditNote
    {
        if ($amount < 1) {
            throw new DomainException('Credit note amount must be positive.');
        }
        if (trim($reason) === '') {
            throw new DomainException('A credit note reason is required.');
        }

        return DB::transaction(function () use ($invoice, $amount, $reason, $actor): CreditNote {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== InvoiceStatus::Issued) {
                throw new DomainException('Only issued invoices can receive a credit note.');
            }
            $credited = (int) $locked->creditNotes()->where('status', 'issued')->sum('amount');
            if ($credited + $amount > $locked->total_amount) {
                throw new DomainException('Credit notes cannot exceed the invoice total.');
            }

            $receivable = LedgerAccount::query()->where('code', '1100')->firstOrFail();
            $revenue = LedgerAccount::query()->where('code', '4000')->firstOrFail();
            $note = CreditNote::create([
                'invoice_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'number' => $this->numbers->next('credit_note', 'CN'),
                'amount' => $amount,
                'currency' => $locked->currency,
                'status' => 'issued',
                'reason' => trim($reason),
                'issued_at' => now(),
                'created_by_id' => $actor->id,
            ]);
            $this->journal->post(
                'Credit note '.$note->number,
                [
                    new JournalLineInput($revenue->id, $locked->currency, debitAmount: $amount),
                    new JournalLineInput($receivable->id, $locked->currency, creditAmount: $amount, customerId: $locked->customer_id),
                ],
                actor: $actor,
                sourceType: CreditNote::class,
                sourceId: (string) $note->id,
            );

            return $note->refresh();
        });
    }
}
