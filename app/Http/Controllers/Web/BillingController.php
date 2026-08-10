<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetInvoiceDetails;
use App\Actions\GetPaymentDetails;
use App\Actions\IssueCreditNote;
use App\Actions\IssueInvoice;
use App\Actions\ListInvoices;
use App\Actions\ListPayments;
use App\Actions\ReversePayment;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function invoices(Request $request, ListInvoices $listInvoices): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('billing.invoices.view'), 403);
        $invoices = $listInvoices->handle(
            $request->string('status')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );
        $rows = $invoices->getCollection()->map(function (mixed $invoice): array {
            if (! $invoice instanceof Invoice) {
                throw new \LogicException('Invoice paginator contained an invalid record.');
            }
            $allocated = $invoice->payments->sum(fn (Payment $payment): int => $payment->allocations
                ->where('invoice_id', $invoice->id)
                ->sum('amount'));
            $credited = $invoice->creditNotes->sum('amount');

            return [
                'public_id' => $invoice->public_id,
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
                'customer' => [
                    'public_id' => $invoice->customer->public_id,
                    'code' => $invoice->customer->code,
                    'name' => $invoice->customer->full_name,
                ],
            ];
        })->values();
        $invoices = new LengthAwarePaginator(
            $rows,
            $invoices->total(),
            $invoices->perPage(),
            $invoices->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Billing/Invoices', [
            'invoices' => $invoices,
            'filters' => $request->only(['status', 'search']),
            'canIssue' => $user->can('billing.invoices.issue'),
        ]);
    }

    public function issue(Request $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        abort_unless($request->user()?->can('billing.invoices.issue') === true, 403);
        $issue->handle($invoice, $request->user());

        return redirect()->route('billing.invoices')->with('success', "Invoice {$invoice->number} issued.");
    }

    public function showInvoice(Request $request, Invoice $invoice, GetInvoiceDetails $getDetails): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('billing.invoices.view'), 403);
        $invoice = $getDetails->handle($invoice);
        $allocated = $invoice->payments->sum(fn (Payment $payment): int => $payment->allocations
            ->where('invoice_id', $invoice->id)
            ->sum('amount'));
        $credited = $invoice->creditNotes->sum('amount');

        return Inertia::render('Billing/InvoiceShow', [
            'invoice' => [
                'public_id' => $invoice->public_id,
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
                'customer' => [
                    'public_id' => $invoice->customer->public_id,
                    'code' => $invoice->customer->code,
                    'name' => $invoice->customer->full_name,
                ],
                'lines' => $invoice->lines->map(fn ($line): array => [
                    'id' => $line->id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_amount' => $line->unit_amount,
                    'total_amount' => $line->total_amount,
                    'currency' => $line->currency,
                    'plan' => $line->plan === null ? null : ['name' => $line->plan->name],
                    'service' => $line->service === null ? null : ['public_id' => $line->service->public_id, 'username' => $line->service->username],
                ])->values(),
                'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                    'public_id' => $payment->public_id,
                    'number' => $payment->number,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'method' => $payment->method,
                    'received_at' => $payment->received_at?->toIso8601String(),
                    'collector' => $payment->actor?->name,
                ])->values(),
                'credit_notes' => $invoice->creditNotes->map(fn ($note): array => [
                    'public_id' => $note->public_id,
                    'number' => $note->number,
                    'amount' => $note->amount,
                    'currency' => $note->currency,
                    'reason' => $note->reason,
                    'issued_at' => $note->issued_at?->toIso8601String(),
                    'creator' => $note->creator?->name,
                ])->values(),
            ],
            'canCredit' => $user->can('billing.adjustments.create') && $invoice->status->value === 'issued',
        ]);
    }

    public function creditNote(Request $request, Invoice $invoice, IssueCreditNote $issue): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('billing.adjustments.create'), 403);
        $validated = $request->validate(['amount' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:2000']]);
        try {
            $note = $issue->handle($invoice, (int) $validated['amount'], (string) $validated['reason'], $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        return redirect()->route('billing.invoices.show', $invoice->public_id)->with('success', "Credit note {$note->number} issued.");
    }

    public function payments(Request $request, ListPayments $listPayments): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('payments.collect'), 403);
        $payments = $listPayments->handle(
            $request->string('status')->toString() ?: null,
            $request->string('method')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );
        $paymentRecords = $payments->getCollection();
        $rows = $paymentRecords->map(function (Payment $payment): array {
            return [
                'public_id' => $payment->public_id,
                'number' => $payment->number,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'received_at' => $payment->received_at?->toIso8601String(),
                'reversed_at' => $payment->reversed_at?->toIso8601String(),
                'collector' => $payment->actor?->name,
                'customer' => [
                    'public_id' => $payment->customer->public_id,
                    'code' => $payment->customer->code,
                    'name' => $payment->customer->full_name,
                ],
                'invoice' => $payment->invoice === null ? null : [
                    'public_id' => $payment->invoice->public_id,
                    'number' => $payment->invoice->number,
                ],
            ];
        })->values();
        $payments = new LengthAwarePaginator(
            $rows,
            $payments->total(),
            $payments->perPage(),
            $payments->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Billing/Payments', [
            'payments' => $payments,
            'filters' => $request->only(['status', 'method', 'search']),
            'canReverse' => $user->can('payments.void'),
        ]);
    }

    public function reversePayment(Request $request, Payment $payment, ReversePayment $reversePayment): RedirectResponse
    {
        abort_unless($request->user()?->can('payments.void') === true, 403);
        $reversePayment->handle($payment, $request->user());

        return redirect()->route('billing.payments')->with('success', "Payment {$payment->number} reversed.");
    }

    public function showPayment(Request $request, Payment $payment, GetPaymentDetails $getDetails): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('payments.collect'), 403);
        $payment = $getDetails->handle($payment);

        return Inertia::render('Billing/PaymentShow', [
            'payment' => [
                'public_id' => $payment->public_id,
                'number' => $payment->number,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'received_at' => $payment->received_at?->toIso8601String(),
                'reversed_at' => $payment->reversed_at?->toIso8601String(),
                'collector' => $payment->actor?->name,
                'cash_shift' => $payment->cashShift?->public_id,
                'customer' => [
                    'public_id' => $payment->customer->public_id,
                    'code' => $payment->customer->code,
                    'name' => $payment->customer->full_name,
                ],
                'invoice' => $payment->invoice === null ? null : ['public_id' => $payment->invoice->public_id, 'number' => $payment->invoice->number],
                'allocations' => $payment->allocations->map(fn (PaymentAllocation $allocation): array => [
                    'id' => $allocation->id,
                    'amount' => $allocation->amount,
                    'currency' => $allocation->currency,
                    'invoice' => ['public_id' => $allocation->invoice->public_id, 'number' => $allocation->invoice->number],
                ])->values(),
            ],
            'canReverse' => $user->can('payments.void'),
        ]);
    }
}
