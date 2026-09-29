<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreatePortalPaymentIntent;
use App\Actions\GenerateInvoicePdf;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PortalBillingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);

        $invoices = Invoice::query()->where('customer_id', $customer->id)->where('status', InvoiceStatus::Issued)->whereNotNull('issued_at')->with(['lines', 'payments.allocations', 'paymentAllocations.payment', 'creditNotes'])->latest('issued_at')->limit(20)->get()->map(fn (Invoice $invoice): array => $this->invoiceSummary($invoice))->values();
        $payments = Payment::query()->where('customer_id', $customer->id)->where('status', PaymentStatus::Posted)->latest('received_at')->limit(20)->get()->map(fn (Payment $payment): array => $this->paymentSummary($payment))->values();

        return response()->json([
            'invoices' => $invoices,
            'payments' => $payments,
            'online_payments' => [
                'enabled' => (string) config('services.payments.driver', 'null') === 'stripe'
                    && is_string(config('services.stripe.publishable_key'))
                    && trim((string) config('services.stripe.publishable_key')) !== '',
                'provider' => (string) config('services.payments.driver', 'null'),
            ],
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $nextDue = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('issued_at')
            ->with(['payments.allocations', 'paymentAllocations.payment', 'creditNotes'])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('id')
            ->lazy(100)
            ->first(fn (Invoice $invoice): bool => $invoice->outstandingAmount() > 0);

        return response()->json([
            'balance' => ['amount' => $customer->balance_amount, 'currency' => $customer->balance_currency],
            'next_due' => $nextDue === null ? null : [
                'invoice_id' => $nextDue->public_id,
                'number' => $nextDue->number,
                'amount' => $nextDue->outstandingAmount(),
                'currency' => $nextDue->currency,
                'due_at' => $nextDue->due_at?->toIso8601String(),
            ],
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('issued_at')
            ->with(['lines', 'payments.allocations', 'paymentAllocations.payment', 'creditNotes'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($validated['per_page'] ?? 25));

        return response()->json([
            'data' => $invoices->getCollection()->map(fn (Invoice $invoice): array => $this->invoiceSummary($invoice))->values(),
            'meta' => ['next_cursor' => $invoices->nextCursor()?->encode(), 'prev_cursor' => $invoices->previousCursor()?->encode(), 'per_page' => $invoices->perPage()],
        ]);
    }

    public function invoice(Request $request, Tenant $tenant, string $invoice): JsonResponse
    {
        return $this->invoiceForCustomer($request, $tenant->id, $invoice);
    }

    public function invoiceRoot(Request $request, string $invoice): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);

        return $this->invoiceForCustomer($request, $customer->tenant_id, $invoice);
    }

    private function invoiceForCustomer(Request $request, int $tenantId, string $invoice): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $model = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->where('public_id', $invoice)
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('issued_at')
            ->with(['lines.plan', 'lines.service', 'payments.allocations', 'paymentAllocations.payment', 'creditNotes'])
            ->firstOrFail();

        return response()->json($this->invoiceSummary($model, true));
    }

    public function invoicePdf(Request $request, Tenant $tenant, string $invoice, GenerateInvoicePdf $generate): Response
    {
        return $this->invoicePdfForCustomer($request, $tenant->id, $invoice, $generate);
    }

    public function invoicePdfRoot(Request $request, string $invoice, GenerateInvoicePdf $generate): Response
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);

        return $this->invoicePdfForCustomer($request, $customer->tenant_id, $invoice, $generate);
    }

    private function invoicePdfForCustomer(Request $request, int $tenantId, string $invoice, GenerateInvoicePdf $generate): Response
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $model = Invoice::query()->where('tenant_id', $tenantId)->where('customer_id', $customer->id)->where('public_id', $invoice)->where('status', InvoiceStatus::Issued)->whereNotNull('issued_at')->firstOrFail();

        return $generate->handle($model);
    }

    public function payments(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $payments = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', PaymentStatus::Posted)
            ->with('invoice')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($validated['per_page'] ?? 25));

        return response()->json([
            'data' => $payments->getCollection()->map(fn (Payment $payment): array => $this->paymentSummary($payment))->values(),
            'meta' => ['next_cursor' => $payments->nextCursor()?->encode(), 'prev_cursor' => $payments->previousCursor()?->encode(), 'per_page' => $payments->perPage()],
        ]);
    }

    public function intent(Request $request, CreatePortalPaymentIntent $createIntent): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $validated = $request->validate(['invoice_id' => ['required', 'string'], 'amount' => ['required', 'integer', 'min:1']]);
        $invoice = Invoice::query()->where('customer_id', $customer->id)->where('public_id', $validated['invoice_id'])->where('status', InvoiceStatus::Issued)->whereNotNull('issued_at')->firstOrFail();
        $intent = $createIntent->handle($customer, $invoice, $validated['amount'], (string) $request->header('X-Idempotency-Key'));

        return response()->json(['id' => $intent->id, 'status' => $intent->status, 'amount' => $intent->amount, 'currency' => $intent->currency, 'payload' => $intent->payload], 201);
    }

    /** @return array<string, mixed> */
    private function invoiceSummary(Invoice $invoice, bool $includeDetails = false): array
    {
        $credited = $invoice->creditNotes->where('status', 'issued')->sum('amount');
        $allocated = $invoice->effectiveAllocatedAmount();
        $summary = [
            'id' => $invoice->public_id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency,
            'total_amount' => $invoice->total_amount,
            'allocated_amount' => $allocated,
            'credited_amount' => $credited,
            'outstanding_amount' => $invoice->outstandingAmount(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => ['description' => $line->description, 'amount' => $line->total_amount, 'currency' => $line->currency])->values(),
        ];
        if (! $includeDetails) {
            return $summary;
        }

        return [
            ...$summary,
            'subtotal_amount' => $invoice->subtotal_amount,
            'tax_amount' => $invoice->tax_amount,
            'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => ['description' => $line->description, 'amount' => $line->total_amount, 'currency' => $line->currency])->values(),
            'payments' => $invoice->payments->map(fn (Payment $payment): array => $this->paymentSummary($payment))->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function paymentSummary(Payment $payment): array
    {
        return [
            'id' => $payment->public_id,
            'number' => $payment->number,
            'status' => $payment->status->value,
            'currency' => $payment->currency,
            'amount' => $payment->amount,
            'invoice_id' => $payment->invoice?->public_id,
            'received_at' => $payment->received_at?->toIso8601String(),
        ];
    }
}
