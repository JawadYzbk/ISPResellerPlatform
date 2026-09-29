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
        foreach (['gross_invoiced_by_currency', 'credited_by_currency', 'net_invoiced_by_currency', 'collected_by_currency', 'current_customer_balances_by_currency', 'outstanding_by_currency'] as $metric) {
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
            foreach ($day['gross_invoiced_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['trend_gross_invoiced:'.$day['date'], $currency, $amount]);
            }
            foreach ($day['credited_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['trend_credited:'.$day['date'], $currency, $amount]);
            }
            foreach ($day['net_invoiced_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['trend_net_invoiced:'.$day['date'], $currency, $amount]);
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
        foreach (['gross_revenue_by_plan', 'gross_revenue_by_zone'] as $metric) {
            foreach ($report[$metric] as $dimension => $amounts) {
                foreach ($amounts as $currency => $amount) {
                    fputcsv($stream, [$metric.':'.$dimension, $currency, $amount]);
                }
            }
        }
        foreach ($report['margin_by_pop'] as $pop => $amounts) {
            foreach ($amounts['gross_revenue_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['gross_revenue_by_pop:'.$pop, $currency, $amount]);
            }
            foreach ($amounts['upstream_cost_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['upstream_cost_by_pop:'.$pop, $currency, $amount]);
            }
            foreach ($amounts['gross_margin_by_currency'] as $currency => $amount) {
                fputcsv($stream, ['gross_margin_by_pop:'.$pop, $currency, $amount]);
            }
        }
        foreach ($report['tax_by_currency'] as $currency => $amount) {
            fputcsv($stream, ['tax_by_currency', $currency, $amount]);
        }
        foreach ($report['cash_collected_per_current_active_customer_by_currency'] as $currency => $amount) {
            fputcsv($stream, ['cash_collected_per_current_active_customer_by_currency', $currency, $amount]);
        }
        fputcsv($stream, ['current_active_customer_count', '', $report['current_active_customer_count']]);
        fputcsv($stream, ['service_termination_events_by_period', '', $report['service_termination_events_by_period']]);
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
