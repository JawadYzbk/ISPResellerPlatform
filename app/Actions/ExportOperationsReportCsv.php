<?php

namespace App\Actions;

use App\Contracts\Action;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class ExportOperationsReportCsv implements Action
{
    public function __construct(private GetOperationsReport $report) {}

    public function handle(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to create the operations report export stream.');
        }

        $report = $this->report->handle($from, $to);
        fputcsv($stream, ['metric', 'status', 'total']);
        fputcsv($stream, ['generated_at', '', $report['generated_at']]);
        foreach (['service_counts_by_status', 'work_order_counts_by_status', 'incident_counts_by_status'] as $metric) {
            foreach ($report[$metric] as $status => $total) {
                fputcsv($stream, [$metric, $status, $total]);
            }
        }
        foreach ($report['low_stock_items'] as $item) {
            fputcsv($stream, ['low_stock_items', $item['sku'], $item['available_units']]);
        }
        foreach (['expiring_services', 'active_sessions', 'offline_routers', 'network_drift', 'failed_commands'] as $metric) {
            fputcsv($stream, [$metric, '', $report[$metric]]);
        }
        foreach ($report['supplier_credentials']['totals'] as $metric => $total) {
            fputcsv($stream, ['supplier_credentials', $metric, $total]);
        }
        foreach ($report['supplier_credentials']['by_supplier'] as $supplier) {
            foreach (['purchased', 'assigned', 'available', 'expiring', 'revoked_invalid'] as $metric) {
                fputcsv($stream, ['supplier:'.$supplier['code'], $metric, $supplier[$metric]]);
            }
            foreach ($supplier['cost_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['supplier_cost:'.$supplier['code'], $currency, $amount]);
            }
            foreach ($supplier['contracts'] as $contract) {
                foreach ($contract['cost_by_currency'] as $currency => $amount) {
                    fputcsv($stream, ['contract:'.$supplier['code'], $contract['reference'] ?? 'unspecified', $currency, $amount]);
                }
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
