<?php

namespace App\Actions;

use App\Contracts\Action;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class ExportSupplierPayablesCsv implements Action
{
    public function __construct(private GetSupplierPayablesAging $report) {}

    public function handle(CarbonImmutable $asOf, ?int $supplierId = null, bool $includeSettled = false): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to create the supplier payables export stream.');
        }

        $report = $this->report->handle($asOf, $supplierId, $includeSettled);
        fputcsv($stream, [
            'as_of',
            'supplier',
            'supplier_code',
            'reference',
            'period_start',
            'period_end',
            'currency',
            'amount',
            'paid_amount',
            'outstanding_amount',
            'age_days',
            'bucket',
            'status',
        ]);
        foreach ($report['bills'] as $bill) {
            fputcsv($stream, [
                $report['as_of'],
                $bill['supplier_name'],
                $bill['supplier_code'],
                $bill['reference'],
                $bill['period_start'],
                $bill['period_end'],
                $bill['currency'],
                $bill['amount'],
                $bill['paid_amount'],
                $bill['outstanding_amount'],
                $bill['age_days'],
                $bill['bucket'],
                $bill['status'],
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
