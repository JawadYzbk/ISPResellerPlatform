<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordPayment;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PaymentApiController extends Controller
{
    public function store(Request $request, RecordPayment $recordPayment): JsonResponse
    {
        abort_unless($request->user()?->can('payments.collect'), 403);
        $validated = $request->validate([
            'customer_id' => ['required', 'string', 'exists:customers,public_id'],
            'invoice_id' => ['nullable', 'string', 'exists:invoices,public_id'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::query()->where('is_active', true)->pluck('code')->all())],
            'method' => ['required', 'string', Rule::in(['cash', 'bank_transfer', 'card', 'mobile_wallet'])],
            'fx_override' => ['sometimes', 'boolean'],
            'fx_rate_numerator' => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn (): bool => $request->boolean('fx_override'))],
            'fx_rate_denominator' => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn (): bool => $request->boolean('fx_override'))],
            'fx_override_reason' => ['nullable', 'string', 'max:500', Rule::requiredIf(fn (): bool => $request->boolean('fx_override'))],
            'reference' => ['nullable', 'string', 'max:128'],
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
            null,
            ($validated['fx_override'] ?? false) ? (int) $validated['fx_rate_numerator'] : null,
            ($validated['fx_override'] ?? false) ? (int) $validated['fx_rate_denominator'] : null,
            ($validated['fx_override'] ?? false) ? (string) $validated['fx_override_reason'] : null,
            isset($validated['reference']) ? (string) $validated['reference'] : null,
        );

        return response()->json([
            'id' => $payment->public_id,
            'number' => $payment->number,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'ledger_amount' => $payment->ledger_amount,
            'ledger_currency' => $payment->ledger_currency,
            'base_amount' => $payment->base_amount,
            'fx_rate_numerator' => $payment->fx_rate_numerator,
            'fx_rate_denominator' => $payment->fx_rate_denominator,
            'fx_rate_overridden' => $payment->fx_rate_overridden,
            'reference' => $payment->reference,
        ], 201);
    }
}
