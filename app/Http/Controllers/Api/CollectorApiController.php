<?php

namespace App\Http\Controllers\Api;

use App\Actions\CloseCashShift;
use App\Actions\GetCollectorShift;
use App\Actions\GetCollectorSummary;
use App\Actions\ListCollectorPayments;
use App\Actions\OpenCashShift;
use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\Payment;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CollectorApiController extends Controller
{
    public function shift(Request $request, GetCollectorShift $getShift): JsonResponse
    {
        return response()->json(['data' => $getShift->handle($this->user($request))]);
    }

    public function openShift(Request $request, OpenCashShift $open, GetCollectorShift $getShift): JsonResponse
    {
        $validated = $request->validate([
            'opening_float' => ['sometimes', 'array'],
            'opening_float.*' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $user = $this->user($request);
            $open->handle($user, $validated['opening_float'] ?? []);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $getShift->handle($user)], 201);
    }

    public function closeShift(Request $request, CloseCashShift $close, GetCollectorShift $getShift): JsonResponse
    {
        $user = $this->user($request);
        $validated = $request->validate([
            'declared_totals' => ['required', 'array', 'min:1'],
            'declared_totals.*' => ['required', 'integer', 'min:0'],
            'variance_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $current = CashShift::query()->where('user_id', $user->id)->where('status', 'open')->latest('opened_at')->first();
        abort_unless($current instanceof CashShift, 409, 'No open cash shift is available.');

        try {
            $shift = $close->handle($current, $validated['declared_totals'], $validated['variance_note'] ?? null, $user);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->closedShift($shift), 'current_shift' => $getShift->handle($user)]);
    }

    public function payments(Request $request, ListCollectorPayments $list): JsonResponse
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $payments = $list->handle($this->user($request), $validated['date'] ?? null, (int) ($validated['per_page'] ?? 50));

        return response()->json([
            'data' => $payments->getCollection()->map(fn (Payment $payment): array => $this->payment($payment))->values(),
            'meta' => ['next_cursor' => $payments->nextCursor()?->encode(), 'prev_cursor' => $payments->previousCursor()?->encode(), 'per_page' => $payments->perPage()],
        ]);
    }

    public function summary(Request $request, GetCollectorSummary $summary): JsonResponse
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['data' => $summary->handle($this->user($request), $validated['date'] ?? now()->toDateString())]);
    }

    /** @return array<string, mixed> */
    private function closedShift(CashShift $shift): array
    {
        return [
            'id' => $shift->public_id,
            'status' => $shift->status->value,
            'opened_at' => $shift->opened_at?->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'opening_float' => $shift->opening_float ?? [],
            'system_totals' => $shift->system_totals ?? [],
            'declared_totals' => $shift->declared_totals ?? [],
            'variance' => $shift->variance,
            'variance_note' => $shift->variance_note,
        ];
    }

    /** @return array<string, mixed> */
    private function payment(Payment $payment): array
    {
        return [
            'id' => $payment->public_id,
            'receipt_number' => $payment->number,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'base_amount' => $payment->base_amount,
            'ledger_amount' => $payment->ledger_amount,
            'ledger_currency' => $payment->ledger_currency,
            'method' => $payment->method,
            'received_at' => $payment->received_at?->toIso8601String(),
            'customer' => ['id' => $payment->customer->public_id, 'name' => $payment->customer->full_name],
            'invoice' => $payment->invoice === null ? null : ['id' => $payment->invoice->public_id, 'number' => $payment->invoice->number],
            'allocations' => $payment->allocations->map(fn ($allocation): array => ['invoice_id' => $allocation->invoice->public_id, 'amount' => $allocation->amount, 'currency' => $allocation->currency])->values(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
