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
        foreach (['billed_by_currency', 'paid_by_currency', 'outstanding_by_currency'] as $metric) {
            foreach ($report['supplier_payables'][$metric] as $currency => $amount) {
                fputcsv($stream, ['supplier_payables_'.$metric, $currency, $amount]);
            }
        }
        foreach ($report['supplier_payables']['aging_by_currency'] as $currency => $buckets) {
            foreach ($buckets as $bucket => $amount) {
                fputcsv($stream, ['supplier_payables_aging_'.$bucket, $currency, $amount]);
            }
        }
        fputcsv($stream, ['supplier_payables_bill_count', '', $report['supplier_payables']['bill_count']]);
        fputcsv($stream, ['supplier_payables_payment_count', '', $report['supplier_payables']['payment_count']]);
        foreach ($report['collection_rate_by_currency'] as $currency => $rate) {
            fputcsv($stream, ['collection_rate_percent', $currency, $rate]);
        }
        foreach ($report['cash_reconciliation']['variance_by_currency'] as $currency => $amount) {
            fputcsv($stream, ['cash_variance_by_currency', $currency, $amount]);
        }
        fputcsv($stream, ['cash_closed_shift_count', '', $report['cash_reconciliation']['closed_shift_count']]);
        fputcsv($stream, ['cash_variance_shift_count', '', $report['cash_reconciliation']['variance_shift_count']]);
        foreach ($report['collection_trend'] as $day) {
            foreach ($day['invoiced_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['trend_invoiced:'.$day['date'], $currency, $amount]);
            }
            foreach ($day['collected_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['trend_collected:'.$day['date'], $currency, $amount]);
            }
        }
        foreach ($report['aging_by_currency'] as $currency => $buckets) {
            foreach ($buckets as $bucket => $amount) {
                fputcsv($stream, ['aging_'.$bucket, $currency, $amount]);
            }
        }
        foreach (['revenue_by_plan', 'revenue_by_zone'] as $metric) {
            foreach ($report[$metric] as $dimension => $amounts) {
                foreach ($amounts as $currency => $amount) {
                    fputcsv($stream, [$metric.':'.$dimension, $currency, $amount]);
                }
            }
        }
        foreach ($report['margin_by_pop'] as $pop => $amounts) {
            foreach ($amounts['revenue_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['revenue_by_pop:'.$pop, $currency, $amount]);
            }
            foreach ($amounts['upstream_cost_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['upstream_cost_by_pop:'.$pop, $currency, $amount]);
            }
            foreach ($amounts['margin_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['margin_by_pop:'.$pop, $currency, $amount]);
            }
        }
        foreach ($report['tax_by_currency'] as $currency => $amount) {
            fputcsv($stream, ['tax_by_currency', $currency, $amount]);
        }
        foreach ($report['arpu_by_currency'] as $currency => $amount) {
            fputcsv($stream, ['arpu_by_currency', $currency, $amount]);
        }
        fputcsv($stream, ['active_customer_count', '', $report['active_customer_count']]);
        fputcsv($stream, ['churned_services', '', $report['churned_services']]);
        foreach ($report['retention_by_period'] as $metric => $value) {
            fputcsv($stream, ['retention:'.$metric, '', $value]);
        }
        foreach ($report['collector_performance'] as $collector) {
            foreach ($collector['totals_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['collector:'.$collector['collector'], $currency, $amount]);
            }
            fputcsv($stream, ['collector_payment_count:'.$collector['collector'], '', $collector['payment_count']]);
        }
        foreach ($report['top_usage'] as $usage) {
            fputcsv($stream, ['top_usage:'.($usage['username'] ?? 'unknown'), '', $usage['total_octets']]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
