<?php

namespace App\Actions;

use App\Contracts\Action;
use RuntimeException;

final readonly class ExportOperationsReportCsv implements Action
{
    public function __construct(private GetOperationsReport $report) {}

    public function handle(): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to create the operations report export stream.');
        }

        $report = $this->report->handle();
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
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
