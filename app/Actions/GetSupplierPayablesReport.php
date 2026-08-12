<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class GetSupplierPayablesReport implements Action
{
    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $bills = SupplierBill::query()
            ->whereDate('period_start', '<=', $to->toDateString())
            ->with(['payments' => fn ($query) => $query->where('paid_at', '<=', $to->endOfDay())])
            ->get();
        $billedByCurrency = [];
        $outstandingByCurrency = [];
        $agingByCurrency = [];
        $billCount = 0;

        foreach ($bills as $bill) {
            $currency = strtoupper($bill->currency);
            if ($bill->period_end->betweenIncluded($from->startOfDay(), $to->endOfDay())) {
                $billedByCurrency[$currency] = ($billedByCurrency[$currency] ?? 0) + $bill->amount;
                $billCount++;
            }

            $paid = (int) $bill->payments->sum('amount');
            $outstanding = max(0, $bill->amount - $paid);
            if ($outstanding === 0) {
                continue;
            }

            $outstandingByCurrency[$currency] = ($outstandingByCurrency[$currency] ?? 0) + $outstanding;
            $bucket = $this->bucket($bill->period_end, $to);
            $agingByCurrency[$currency] ??= ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
            $agingByCurrency[$currency][$bucket] += $outstanding;
        }

        $payments = SupplierPayment::query()
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($value): int => (int) $value)
            ->all();
        $paymentCount = (int) SupplierPayment::query()
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->count();

        return [
            'bill_count' => $billCount,
            'payment_count' => $paymentCount,
            'billed_by_currency' => $billedByCurrency,
            'paid_by_currency' => $payments,
            'outstanding_by_currency' => $outstandingByCurrency,
            'aging_by_currency' => $agingByCurrency,
        ];
    }

    /** @return 'current'|'1_30'|'31_60'|'61_90'|'90_plus' */
    private function bucket(CarbonInterface $dueAt, CarbonInterface $asOf): string
    {
        if ($dueAt->startOfDay()->greaterThanOrEqualTo($asOf->startOfDay())) {
            return 'current';
        }

        return match (true) {
            $dueAt->startOfDay()->diffInDays($asOf->startOfDay()) <= 30 => '1_30',
            $dueAt->startOfDay()->diffInDays($asOf->startOfDay()) <= 60 => '31_60',
            $dueAt->startOfDay()->diffInDays($asOf->startOfDay()) <= 90 => '61_90',
            default => '90_plus',
        };
    }
}
