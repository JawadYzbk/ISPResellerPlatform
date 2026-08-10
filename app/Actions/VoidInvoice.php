<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class VoidInvoice implements Action
{
    public function __construct(private PostJournalEntry $journal) {}

    public function handle(Invoice $invoice, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === InvoiceStatus::Void) {
                throw new DomainException('The invoice is already void.');
            }
            if ($invoice->status === InvoiceStatus::Issued) {
                if ($invoice->creditNotes()->where('status', 'issued')->exists()) {
                    throw new DomainException('An invoice with issued credit notes cannot be voided.');
                }
                $receivable = LedgerAccount::query()->where('code', '1100')->firstOrFail();
                $revenue = LedgerAccount::query()->where('code', '4000')->firstOrFail();
                $this->journal->post(
                    'Void invoice '.$invoice->number,
                    [
                        new JournalLineInput($receivable->id, $invoice->currency, creditAmount: $invoice->total_amount, customerId: $invoice->customer_id),
                        new JournalLineInput($revenue->id, $invoice->currency, debitAmount: $invoice->total_amount),
                    ],
                    actor: $actor,
                    sourceType: Invoice::class.':void',
                    sourceId: (string) $invoice->id,
                );
            }
            $invoice->forceFill(['status' => InvoiceStatus::Void, 'voided_at' => now()])->save();

            return $invoice->refresh();
        });
    }
}
