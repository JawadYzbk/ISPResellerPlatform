<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\PaymentStatus;
use App\Models\CashShift;
use App\Models\Payment;

final readonly class GetCashShiftDailyReport implements Action
{
    /** @return array<string, mixed> */
    public function handle(string $date): array
    {
        $payments = Payment::query()
            ->with('actor')
            ->where('status', PaymentStatus::Posted)
            ->whereNotNull('cash_shift_id')
            ->whereDate('received_at', $date)
            ->get();
        $totals = [];
        $collectors = [];

        foreach ($payments as $payment) {
            $currency = $payment->currency;
            $totals[$currency] = ($totals[$currency] ?? 0) + $payment->amount;
            $key = (string) ($payment->actor_id ?? 0);
            $collectors[$key] ??= ['name' => optional($payment->actor)->name ?? 'System', 'payment_count' => 0, 'totals' => []];
            $collectors[$key]['payment_count']++;
            $collectors[$key]['totals'][$currency] = ($collectors[$key]['totals'][$currency] ?? 0) + $payment->amount;
        }

        $varianceShiftCount = CashShift::query()
            ->where('variance', true)
            ->whereHas('payments', fn ($query) => $query->where('status', PaymentStatus::Posted)->whereDate('received_at', $date))
            ->count();

        return ['date' => $date, 'payment_count' => $payments->count(), 'totals' => $totals, 'variance_shift_count' => $varianceShiftCount, 'collectors' => array_values($collectors)];
    }
}
