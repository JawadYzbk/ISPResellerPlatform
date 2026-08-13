<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\PaymentStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;

final readonly class GetPublicBillingPageData implements Action
{
    public function __construct(
        private ResolvePublicBillingLink $resolve,
        private GetPaymentSetupStatus $paymentSetup,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $token): array
    {
        $link = $this->resolve->handle($token);
        $customer = $link->customer;
        $tenant = $link->tenant;
        $invoice = $link->invoice;
        $payment = $link->payment;

        return [
            'type' => $link->type,
            'expires_at' => $link->expires_at->toIso8601String(),
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo_url' => $tenant->logoUrl(),
            ],
            'customer' => [
                'code' => $customer->code,
                'name' => $customer->full_name,
                'balance_amount' => $customer->balance_amount,
                'balance_currency' => $customer->balance_currency,
            ],
            'invoice' => $invoice instanceof Invoice ? $this->invoice($invoice) : null,
            'payment' => $payment instanceof Payment ? $this->payment($payment) : null,
            'statement' => $link->type === 'statement' ? $this->statement($customer->id) : null,
            'gateways' => $link->type === 'payment' ? $this->paymentSetup->handle() : null,
        ];
    }

    /** @return array<string, mixed> */
    private function invoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines', 'payments.allocations', 'creditNotes']);
        $allocated = (int) PaymentAllocation::query()->where('invoice_id', $invoice->id)->sum('amount');
        $credited = (int) CreditNote::query()->where('invoice_id', $invoice->id)->where('status', 'issued')->sum('amount');

        return [
            'public_id' => $invoice->public_id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency,
            'subtotal_amount' => $invoice->subtotal_amount,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'outstanding_amount' => max(0, $invoice->total_amount - $allocated - $credited),
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'lines' => $invoice->lines->map(fn ($line): array => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'amount' => $line->total_amount,
                'currency' => $line->currency,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function payment(Payment $payment): array
    {
        $payment->loadMissing(['allocations.invoice']);

        return [
            'public_id' => $payment->public_id,
            'number' => $payment->number,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'received_at' => $payment->received_at?->toIso8601String(),
            'allocations' => $payment->allocations->map(fn ($allocation): array => [
                'invoice_number' => $allocation->invoice->number,
                'amount' => $allocation->amount,
                'currency' => $allocation->currency,
            ])->values()->all(),
        ];
    }

    /** @return array{invoices: list<array<string, mixed>>, payments: list<array<string, mixed>>} */
    private function statement(int $customerId): array
    {
        $invoices = Invoice::query()
            ->where('customer_id', $customerId)
            ->with(['lines', 'payments.allocations', 'creditNotes'])
            ->latest('issued_at')
            ->limit(24)
            ->get()
            ->map(fn (Invoice $invoice): array => $this->invoice($invoice))
            ->values()
            ->all();
        $payments = Payment::query()
            ->where('customer_id', $customerId)
            ->where('status', PaymentStatus::Posted)
            ->with('allocations.invoice')
            ->latest('received_at')
            ->limit(24)
            ->get()
            ->map(fn (Payment $payment): array => $this->payment($payment))
            ->values()
            ->all();

        return ['invoices' => $invoices, 'payments' => $payments];
    }
}
