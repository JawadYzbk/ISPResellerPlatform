<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordPayment;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentApiController extends Controller
{
    public function store(Request $request, RecordPayment $recordPayment): JsonResponse
    {
        abort_unless($request->user()?->can('payments.collect'), 403);
        $validated = $request->validate([
            'customer_id' => ['required', 'string', 'exists:customers,public_id'],
            'invoice_id' => ['nullable', 'string', 'exists:invoices,public_id'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'method' => ['required', 'string', 'max:40'],
        ]);
        $customer = Customer::query()->where('public_id', $validated['customer_id'])->firstOrFail();
        $invoice = isset($validated['invoice_id'])
            ? Invoice::query()->where('public_id', $validated['invoice_id'])->firstOrFail()
            : null;
        $payment = $recordPayment->handle(
            $customer,
            $validated['amount'],
            strtoupper($validated['currency']),
            $validated['method'],
            $request->header('X-Idempotency-Key'),
            $invoice,
            $request->user(),
        );

        return response()->json([
            'id' => $payment->public_id,
            'number' => $payment->number,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ], 201);
    }
}
