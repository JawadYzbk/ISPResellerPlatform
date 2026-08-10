<?php

namespace App\Actions;

use App\Contracts\Action;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class ExportFinanceReportCsv implements Action
{
    public function __construct(private GetFinanceReport $report) {}

    public function handle(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to create the finance report export stream.');
        }
        $report = $this->report->handle($from, $to);
        fputcsv($stream, ['metric', 'currency', 'value']);
        fputcsv($stream, ['from', '', $report['from']]);
        fputcsv($stream, ['to', '', $report['to']]);
        fputcsv($stream, ['invoice_count', '', $report['invoice_count']]);
        fputcsv($stream, ['payment_count', '', $report['payment_count']]);
        foreach (['invoiced_by_currency', 'collected_by_currency', 'customer_balances_by_currency', 'outstanding_by_currency'] as $metric) {
            foreach ($report[$metric] as $currency => $amount) {
                fputcsv($stream, [$metric, $currency, $amount]);
            }
        }
        foreach ($report['collection_rate_by_currency'] as $currency => $rate) {
            fputcsv($stream, ['collection_rate_percent', $currency, $rate]);
        }
        foreach ($report['aging_by_currency'] as $currency => $buckets) {
            foreach ($buckets as $bucket => $amount) {
                fputcsv($stream, ['aging_'.$bucket, $currency, $amount]);
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
