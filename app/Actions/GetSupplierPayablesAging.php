<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\SupplierBill;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class GetSupplierPayablesAging implements Action
{
    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $asOf, ?int $supplierId = null, bool $includeSettled = false): array
    {
        $bills = SupplierBill::query()
            ->whereDate('period_start', '<=', $asOf->toDateString())
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->with([
                'supplier:id,name,code',
                'payments' => fn ($query) => $query->where('paid_at', '<=', $asOf->endOfDay())->orderBy('paid_at'),
            ])
            ->orderBy('period_end')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($bills as $bill) {
            $paidAmount = (int) $bill->payments->sum('amount');
            $outstandingAmount = max(0, (int) $bill->amount - $paidAmount);
            if (! $includeSettled && $outstandingAmount === 0) {
                continue;
            }

            $bucket = $this->bucket($bill->period_end, $asOf);
            $rows[] = [
                'id' => $bill->id,
                'supplier_id' => $bill->supplier_id,
                'supplier_name' => $bill->supplier->name,
                'supplier_code' => $bill->supplier->code,
                'reference' => $bill->reference,
                'period_start' => $bill->period_start->toDateString(),
                'period_end' => $bill->period_end->toDateString(),
                'currency' => strtoupper($bill->currency),
                'amount' => (int) $bill->amount,
                'paid_amount' => $paidAmount,
                'outstanding_amount' => $outstandingAmount,
                'age_days' => max(0, $bill->period_end->startOfDay()->diffInDays($asOf->startOfDay())),
                'bucket' => $bucket,
                'status' => $outstandingAmount === 0 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'open'),
                'last_paid_at' => $bill->payments->last()?->paid_at?->toIso8601String(),
            ];
        }

        $summary = [
            'bill_count' => count($rows),
            'open_bill_count' => count(array_filter($rows, fn (array $row): bool => $row['outstanding_amount'] > 0)),
            'billed_by_currency' => [],
            'paid_by_currency' => [],
            'outstanding_by_currency' => [],
            'aging_by_currency' => [],
        ];
        $bySupplier = [];

        foreach ($rows as $row) {
            $currency = $row['currency'];
            $summary['billed_by_currency'][$currency] = ($summary['billed_by_currency'][$currency] ?? 0) + $row['amount'];
            $summary['paid_by_currency'][$currency] = ($summary['paid_by_currency'][$currency] ?? 0) + $row['paid_amount'];
            $summary['outstanding_by_currency'][$currency] = ($summary['outstanding_by_currency'][$currency] ?? 0) + $row['outstanding_amount'];
            $summary['aging_by_currency'][$currency] ??= $this->emptyBuckets();
            $summary['aging_by_currency'][$currency][$row['bucket']] += $row['outstanding_amount'];

            $supplierKey = (string) $row['supplier_id'];
            $bySupplier[$supplierKey] ??= [
                'supplier_id' => $row['supplier_id'],
                'supplier_name' => $row['supplier_name'],
                'supplier_code' => $row['supplier_code'],
                'bill_count' => 0,
                'outstanding_by_currency' => [],
                'aging_by_currency' => [],
            ];
            $bySupplier[$supplierKey]['bill_count']++;
            $bySupplier[$supplierKey]['outstanding_by_currency'][$currency] = ($bySupplier[$supplierKey]['outstanding_by_currency'][$currency] ?? 0) + $row['outstanding_amount'];
            $bySupplier[$supplierKey]['aging_by_currency'][$currency] ??= $this->emptyBuckets();
            $bySupplier[$supplierKey]['aging_by_currency'][$currency][$row['bucket']] += $row['outstanding_amount'];
        }

        return [
            'as_of' => $asOf->toDateString(),
            'supplier_id' => $supplierId,
            'include_settled' => $includeSettled,
            'summary' => $summary,
            'by_supplier' => array_values($bySupplier),
            'bills' => $rows,
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

    /** @return array<string, int> */
    private function emptyBuckets(): array
    {
        return ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
    }
}
