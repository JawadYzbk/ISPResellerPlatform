<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Enums\CashShiftStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class RecordPayment implements Action
{
    public function __construct(private DocumentNumberGenerator $numbers, private PostJournalEntry $journal, private RenewService $renewService, private QueueCustomerNotification $notify) {}

    public function handle(Customer $customer, int $amount, string $currency, string $method, string $idempotencyKey, ?Invoice $invoice = null, ?User $actor = null, ?CashShift $cashShift = null): Payment
    {
        if ($amount < 1) {
            throw new DomainException('Payment amount must be positive.');
        }
        if ($customer->balance_currency !== $currency) {
            throw new DomainException('Payment currency must match the customer ledger currency until an FX snapshot is provided.');
        }
        if ($invoice !== null && ($invoice->tenant_id !== $customer->tenant_id || $invoice->customer_id !== $customer->id || $invoice->status !== InvoiceStatus::Issued || $invoice->currency !== $currency)) {
            throw new DomainException('The invoice is not payable by this customer in this currency.');
        }
        if ($cashShift !== null && $cashShift->status !== CashShiftStatus::Open) {
            throw new DomainException('Payments cannot be recorded to a closed cash shift.');
        }

        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof Payment) {
            return $existing->load('allocations');
        }

        try {
            $payment = DB::transaction(function () use ($customer, $amount, $currency, $method, $idempotencyKey, $invoice, $actor, $cashShift): Payment {
                $cash = LedgerAccount::query()->where('code', '1000')->firstOrFail();
                $receivable = LedgerAccount::query()->where('code', '1100')->firstOrFail();
                $payment = Payment::create([
                    'number' => $this->numbers->next('receipt', 'RCT'),
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice?->id,
                    'cash_shift_id' => $cashShift?->id,
                    'status' => PaymentStatus::Posted,
                    'amount' => $amount,
                    'currency' => $currency,
                    'method' => $method,
                    'idempotency_key' => $idempotencyKey,
                    'received_at' => now(),
                    'actor_id' => $actor?->id,
                ]);
                if ($invoice !== null) {
                    $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => min($amount, $invoice->total_amount), 'currency' => $currency]);
                }
                $this->journal->post(
                    'Payment '.$payment->number,
                    [
                        new JournalLineInput($cash->id, $currency, debitAmount: $amount),
                        new JournalLineInput($receivable->id, $currency, creditAmount: $amount, customerId: $customer->id),
                    ],
                    actor: $actor,
                    sourceType: Payment::class,
                    sourceId: (string) $payment->id,
                );

                if ($invoice !== null) {
                    $invoice->loadMissing('lines.service');
                    foreach ($invoice->lines as $line) {
                        if ($line->service !== null) {
                            $this->renewService->handle($line->service, $actor);
                        }
                    }
                }

                return $payment->load('allocations');
            });

            $this->notify->handle($customer, 'payment.receipt', 'payment-receipt:'.$payment->id, [
                'customer_name' => $customer->full_name,
                'receipt_number' => $payment->number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]);

            return $payment;
        } catch (UniqueConstraintViolationException) {
            return Payment::query()->where('idempotency_key', $idempotencyKey)->firstOrFail()->load('allocations');
        }
    }
}
