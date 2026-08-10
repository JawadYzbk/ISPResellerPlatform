<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreatePortalPaymentIntent;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PortalBillingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);

        $invoices = Invoice::query()->where('customer_id', $customer->id)->with('lines')->latest('issued_at')->limit(20)->get()->map(fn (Invoice $invoice): array => [
            'id' => $invoice->public_id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency,
            'total_amount' => $invoice->total_amount,
            'due_at' => $invoice->due_at?->toIso8601String(),
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => ['description' => $line->description, 'amount' => $line->total_amount, 'currency' => $line->currency])->values(),
        ])->values();
        $payments = Payment::query()->where('customer_id', $customer->id)->latest('received_at')->limit(20)->get()->map(fn (Payment $payment): array => [
            'id' => $payment->public_id,
            'number' => $payment->number,
            'status' => $payment->status->value,
            'currency' => $payment->currency,
            'amount' => $payment->amount,
            'received_at' => $payment->received_at?->toIso8601String(),
        ])->values();

        return response()->json(['invoices' => $invoices, 'payments' => $payments]);
    }

    public function intent(Request $request, CreatePortalPaymentIntent $createIntent): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $validated = $request->validate(['invoice_id' => ['required', 'string'], 'amount' => ['required', 'integer', 'min:1']]);
        $invoice = Invoice::query()->where('customer_id', $customer->id)->where('public_id', $validated['invoice_id'])->firstOrFail();
        $intent = $createIntent->handle($customer, $invoice, $validated['amount'], (string) $request->header('X-Idempotency-Key'));

        return response()->json(['id' => $intent->id, 'status' => $intent->status, 'amount' => $intent->amount, 'currency' => $intent->currency, 'payload' => $intent->payload], 201);
    }
}
