<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetPaymentDetails;
use App\Actions\ListPaymentsApi;
use App\Actions\RecordPayment;
use App\Enums\FxRoundingMode;
use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Api\PaymentApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PaymentApiController extends Controller
{
    public function index(Request $request, ListPaymentsApi $listPayments): JsonResponse
    {
        abort_unless($request->user()?->can('payments.collect'), 403);

        return response()->json($listPayments->handle($request, $request->integer('per_page', 20)));
    }

    public function show(Request $request, string $payment, GetPaymentDetails $getDetails, PaymentApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('payments.collect'), 403);
        $model = Payment::query()->where('public_id', $payment)->firstOrFail();

        return response()->json($resource->make($getDetails->handle($model)));
    }

    public function store(Request $request, RecordPayment $recordPayment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('payments.collect'), 403);
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
            'rounding_mode' => ['nullable', 'string', Rule::in(array_map(fn (FxRoundingMode $mode): string => $mode->value, FxRoundingMode::cases()))],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);
        $customer = Customer::query()->where('public_id', $validated['customer_id'])->firstOrFail();
        $invoice = isset($validated['invoice_id'])
            ? Invoice::query()->where('public_id', $validated['invoice_id'])->firstOrFail()
            : null;
        $cashShift = null;
        if ($user->tokenCan('staff:collector')) {
            $cashShift = CashShift::query()->where('user_id', $user->id)->where('status', 'open')->latest('opened_at')->first();
            if (! $cashShift instanceof CashShift) {
                $replayed = Payment::query()->where('idempotency_key', $request->header('X-Idempotency-Key'))->first();
                abort_unless($replayed instanceof Payment, 423, 'An open cash shift is required before recording collector payments.');
            }
        }
        $payment = $recordPayment->handle(
            $customer,
            $validated['amount'],
            strtoupper($validated['currency']),
            $validated['method'],
            $request->header('X-Idempotency-Key'),
            $invoice,
            $user,
            $cashShift,
            ($validated['fx_override'] ?? false) ? (int) $validated['fx_rate_numerator'] : null,
            ($validated['fx_override'] ?? false) ? (int) $validated['fx_rate_denominator'] : null,
            ($validated['fx_override'] ?? false) ? (string) $validated['fx_override_reason'] : null,
            isset($validated['reference']) ? (string) $validated['reference'] : null,
            isset($validated['rounding_mode']) ? (string) $validated['rounding_mode'] : null,
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
            'fx_rounding_mode' => $payment->metadata['base_fx_snapshot']['rounding_mode'] ?? null,
            'fx_rate_source' => $payment->metadata['base_fx_snapshot']['rate_source'] ?? $payment->metadata['base_fx_source'] ?? null,
            'fx_rate_effective_from' => $payment->metadata['base_fx_snapshot']['effective_from'] ?? null,
            'reference' => $payment->reference,
        ], 201);
    }
}
