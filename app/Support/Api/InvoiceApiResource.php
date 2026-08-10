<?php

namespace App\Support\Api;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;

final readonly class InvoiceApiResource
{
    /** @return array<string, mixed> */
    public function make(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'customer',
            'lines.plan',
            'lines.service',
            'payments.actor',
            'payments.allocations',
            'creditNotes.creator',
        ]);
        $allocated = $invoice->payments->sum(fn (Payment $payment): int => $payment->allocations
            ->where('invoice_id', $invoice->id)
            ->sum('amount'));
        $credited = $invoice->creditNotes->where('status', 'issued')->sum('amount');

        return [
            'id' => $invoice->public_id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency,
            'subtotal_amount' => $invoice->subtotal_amount,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'allocated_amount' => $allocated,
            'credited_amount' => $credited,
            'outstanding_amount' => max(0, $invoice->total_amount - $allocated - $credited),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'voided_at' => $invoice->voided_at?->toIso8601String(),
            'customer' => $invoice->customer === null ? null : [
                'id' => $invoice->customer->public_id,
                'code' => $invoice->customer->code,
                'name' => $invoice->customer->full_name,
            ],
            'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_amount' => $line->unit_amount,
                'total_amount' => $line->total_amount,
                'currency' => $line->currency,
                'plan' => $line->plan === null ? null : ['id' => $line->plan->public_id, 'name' => $line->plan->name],
                'service' => $line->service === null ? null : ['id' => $line->service->public_id, 'username' => $line->service->username],
            ])->values()->all(),
            'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                'id' => $payment->public_id,
                'number' => $payment->number,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'received_at' => $payment->received_at?->toIso8601String(),
                'collector' => $payment->actor?->name,
            ])->values()->all(),
            'credit_notes' => $invoice->creditNotes->map(fn (CreditNote $note): array => [
                'id' => $note->public_id,
                'number' => $note->number,
                'status' => $note->status,
                'amount' => $note->amount,
                'currency' => $note->currency,
                'reason' => $note->reason,
                'issued_at' => $note->issued_at?->toIso8601String(),
                'creator' => $note->creator?->name,
            ])->values()->all(),
        ];
    }
}
