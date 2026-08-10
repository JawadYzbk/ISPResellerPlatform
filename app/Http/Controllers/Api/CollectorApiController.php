<?php

namespace App\Http\Controllers\Api;

use App\Actions\CloseCashShift;
use App\Actions\GetCollectorCustomer;
use App\Actions\GetCollectorShift;
use App\Actions\GetCollectorSummary;
use App\Actions\ListCollectorCustomers;
use App\Actions\ListCollectorPayments;
use App\Actions\OpenCashShift;
use App\Actions\ResendPaymentReceipt;
use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function customers(Request $request, ListCollectorCustomers $list): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'zone' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:32'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $statuses = array_values(array_filter(explode(',', (string) ($validated['status'] ?? '')), static fn (string $status): bool => in_array($status, ['due', 'overdue'], true)));
        $customers = $list->handle($this->user($request), $validated['q'] ?? null, $validated['zone'] ?? null, $statuses, (int) ($validated['per_page'] ?? 50));

        return response()->json([
            'data' => $customers->getCollection()->map(fn (Customer $customer): array => $this->customerRow($customer))->values(),
            'meta' => ['next_cursor' => $customers->nextCursor()?->encode(), 'prev_cursor' => $customers->previousCursor()?->encode(), 'per_page' => $customers->perPage()],
        ]);
    }

    public function customer(string $customer, GetCollectorCustomer $get): JsonResponse
    {
        $customerModel = Customer::query()->where('public_id', $customer)->firstOrFail();
        $this->authorize('view', $customerModel);

        return response()->json(['data' => $get->handle($customerModel)]);
    }

    public function resendReceipt(Request $request, string $payment, ResendPaymentReceipt $resend): JsonResponse
    {
        $validated = $request->validate(['channel' => ['required', 'string', Rule::in(['whatsapp', 'sms', 'email'])]]);
        $paymentModel = Payment::query()->where('public_id', $payment)->firstOrFail();

        try {
            $message = $resend->handle($paymentModel, $this->user($request), $validated['channel'], (string) $request->header('X-Idempotency-Key'));
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['status' => $message === null ? 'not_queued' : 'queued', 'message_id' => $message?->public_id], 202);
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

    /** @return array<string, mixed> */
    private function customerRow(Customer $customer): array
    {
        $now = now();
        $service = $customer->services
            ->filter(fn ($service): bool => $service->status->value !== 'terminated')
            ->sortBy('expires_at')
            ->first();

        return [
            'id' => $customer->public_id,
            'code' => $customer->code,
            'name' => $customer->full_name,
            'phone' => $customer->phone,
            'zone' => $customer->zone?->code,
            'balance' => ['amount' => $customer->balance_amount, 'currency' => $customer->balance_currency],
            'next_expires_at' => $service?->expires_at?->toIso8601String(),
            'due_state' => $service?->expires_at === null ? null : ($service->expires_at->isPast() ? 'overdue' : ($service->expires_at->lte($now->copy()->addDays(7)) ? 'due' : 'current')),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
