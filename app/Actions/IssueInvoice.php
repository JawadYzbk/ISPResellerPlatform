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

final readonly class IssueInvoice implements Action
{
    public function __construct(private PostJournalEntry $journal) {}

    public function handle(Invoice $invoice, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $invoice = Invoice::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw new DomainException('Only draft invoices can be issued.');
            }
            $receivable = LedgerAccount::query()->where('code', '1100')->firstOrFail();
            $revenue = LedgerAccount::query()->where('code', '4000')->firstOrFail();
            $this->journal->post(
                'Invoice '.$invoice->number,
                [
                    new JournalLineInput($receivable->id, $invoice->currency, debitAmount: $invoice->total_amount, customerId: $invoice->customer_id),
                    new JournalLineInput($revenue->id, $invoice->currency, creditAmount: $invoice->total_amount),
                ],
                actor: $actor,
                sourceType: Invoice::class,
                sourceId: (string) $invoice->id,
            );
            $invoice->forceFill(['status' => InvoiceStatus::Issued, 'issued_at' => now()])->save();

            return $invoice->refresh();
        });
    }
}
