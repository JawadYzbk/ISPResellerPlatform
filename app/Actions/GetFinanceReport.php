<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\UpstreamLink;
use App\Models\UsageDaily;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class GetFinanceReport implements Action
{
    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $invoices = Invoice::query()->where('status', InvoiceStatus::Issued)->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()]);
        $payments = Payment::query()->where('status', PaymentStatus::Posted)->whereBetween('received_at', [$from->startOfDay(), $to->endOfDay()]);
        $grossInvoicedByCurrency = $invoices->clone()->selectRaw('currency, SUM(total_amount) as total')->groupBy('currency')->pluck('total', 'currency')->map(fn ($value): int => (int) $value)->all();
        $creditedByCurrency = CreditNote::query()->where('status', 'issued')->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()])->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency')->map(fn ($value): int => (int) $value)->all();
        $invoicedByCurrency = [];
        foreach (array_unique([...array_keys($grossInvoicedByCurrency), ...array_keys($creditedByCurrency)]) as $currency) {
            $invoicedByCurrency[$currency] = max(0, ($grossInvoicedByCurrency[$currency] ?? 0) - ($creditedByCurrency[$currency] ?? 0));
        }
        $collectedByCurrency = $payments->clone()->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency')->map(fn ($value): int => (int) $value)->all();
        $collectionRates = [];
        foreach (array_unique([...array_keys($invoicedByCurrency), ...array_keys($collectedByCurrency)]) as $currency) {
            $invoiced = $invoicedByCurrency[$currency] ?? 0;
            $collected = $collectedByCurrency[$currency] ?? 0;
            $collectionRates[$currency] = $invoiced === 0 ? null : round(($collected / $invoiced) * 100, 2);
        }
        $aging = $this->aging($to);
        $breakdowns = $this->breakdowns(
            $invoices->clone()->with(['customer.zone', 'lines.plan', 'lines.service.router.pop'])->get(),
            $from,
            $to,
            $collectedByCurrency,
            $payments->clone()->with('cashShift.user')->get(),
        );

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'invoice_count' => (int) $invoices->count(),
            'payment_count' => (int) $payments->count(),
            'invoiced_by_currency' => $invoicedByCurrency,
            'credited_by_currency' => $creditedByCurrency,
            'collected_by_currency' => $collectedByCurrency,
            'collection_rate_by_currency' => $collectionRates,
            'aging_by_currency' => $aging['aging_by_currency'],
            'outstanding_by_currency' => $aging['outstanding_by_currency'],
            'customer_balances_by_currency' => Customer::query()->selectRaw('balance_currency, SUM(balance_amount) as total')->groupBy('balance_currency')->pluck('total', 'balance_currency')->map(fn ($value): int => (int) $value)->all(),
            ...$breakdowns,
        ];
    }

    /** @param Collection<int, Invoice> $invoices @param array<string, int> $collectedByCurrency @param Collection<int, Payment> $payments @return array<string, mixed> */
    private function breakdowns(Collection $invoices, CarbonImmutable $from, CarbonImmutable $to, array $collectedByCurrency, Collection $payments): array
    {
        $revenueByPlan = [];
        $revenueByZone = [];
        $revenueByPop = [];
        $taxByCurrency = [];
        foreach ($invoices as $invoice) {
            $currency = $invoice->currency;
            $taxByCurrency[$currency] = ($taxByCurrency[$currency] ?? 0) + $invoice->tax_amount;
            $zone = (string) ($invoice->customer?->zone?->getAttribute('code') ?? 'unassigned');
            $revenueByZone[$zone][$currency] = ($revenueByZone[$zone][$currency] ?? 0) + $invoice->total_amount;
            foreach ($invoice->lines as $line) {
                $plan = (string) ($line->plan?->getAttribute('slug') ?? 'unassigned');
                $revenueByPlan[$plan][$currency] = ($revenueByPlan[$plan][$currency] ?? 0) + $line->total_amount;
                $pop = (string) ($line->service?->router?->pop?->getAttribute('code') ?? 'unassigned');
                $revenueByPop[$pop][$currency] = ($revenueByPop[$pop][$currency] ?? 0) + $line->total_amount;
            }
        }

        $activeCustomerCount = (int) Service::query()->where('status', 'active')->distinct()->count('customer_id');
        $arpu = [];
        foreach ($collectedByCurrency as $currency => $amount) {
            $arpu[$currency] = $activeCustomerCount === 0 ? null : round($amount / $activeCustomerCount, 2);
        }

        return [
            'revenue_by_plan' => $revenueByPlan,
            'revenue_by_zone' => $revenueByZone,
            'margin_by_pop' => $this->marginByPop($revenueByPop, $from, $to),
            'tax_by_currency' => $taxByCurrency,
            'churned_services' => ServiceEvent::query()->where('to_status', 'terminated')->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->count(),
            'retention_by_period' => $this->retention($from, $to),
            'active_customer_count' => $activeCustomerCount,
            'arpu_by_currency' => $arpu,
            'top_usage' => $this->topUsage($from, $to),
            'collector_performance' => $this->collectorPerformance($payments),
        ];
    }

    /** @param array<string, array<string, int>> $revenueByPop @return array<string, array<string, array<string, int>>> */
    private function marginByPop(array $revenueByPop, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $costByPop = [];
        UpstreamLink::query()
            ->with('pop')
            ->whereDate('contract_start', '<=', $to->toDateString())
            ->where(fn (Builder $query): Builder => $query->whereNull('contract_end')->orWhereDate('contract_end', '>=', $from->toDateString()))
            ->get()
            ->each(function (UpstreamLink $link) use (&$costByPop, $from, $to): void {
                $start = CarbonImmutable::parse($link->getAttribute('contract_start'))->max($from->startOfDay());
                $end = CarbonImmutable::parse($link->getAttribute('contract_end') ?? $to->toDateString())->min($to->endOfDay());
                if ($end->lessThan($start)) {
                    return;
                }
                $cost = $this->proratedMonthlyCost($link->monthly_cost_amount, $start, $end);
                $pop = (string) ($link->pop?->getAttribute('code') ?? 'unassigned');
                $costByPop[$pop][$link->currency] = ($costByPop[$pop][$link->currency] ?? 0) + $cost;
            });

        $pops = array_unique([...array_keys($revenueByPop), ...array_keys($costByPop)]);
        $report = [];
        foreach ($pops as $pop) {
            $revenue = $revenueByPop[$pop] ?? [];
            $cost = $costByPop[$pop] ?? [];
            $margin = [];
            foreach (array_unique([...array_keys($revenue), ...array_keys($cost)]) as $currency) {
                $margin[$currency] = ($revenue[$currency] ?? 0) - ($cost[$currency] ?? 0);
            }
            $report[$pop] = [
                'revenue_by_currency' => $revenue,
                'upstream_cost_by_currency' => $cost,
                'margin_by_currency' => $margin,
            ];
        }

        return $report;
    }

    private function proratedMonthlyCost(int $monthlyCost, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $total = 0;
        for ($month = $start->startOfMonth(); $month->lessThanOrEqualTo($end); $month = $month->addMonth()->startOfMonth()) {
            $monthStart = $month->greaterThan($start) ? $month : $start;
            $monthEnd = $month->endOfMonth()->lessThan($end) ? $month->endOfMonth() : $end;
            $days = $monthStart->startOfDay()->diffInDays($monthEnd->startOfDay()) + 1;
            $total += (int) round($monthlyCost * ($days / $month->daysInMonth));
        }

        return $total;
    }

    /** @return array{active_at_period_start: int, terminated_services: int, retention_rate_percent: float|null} */
    private function retention(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $terminatedBeforeStart = ServiceEvent::query()
            ->where('to_status', 'terminated')
            ->where('created_at', '<=', $from->endOfDay())
            ->pluck('service_id');
        $activeAtPeriodStart = (int) Service::query()
            ->where('created_at', '<=', $from->endOfDay())
            ->whereNotIn('id', $terminatedBeforeStart)
            ->count();
        $terminated = (int) ServiceEvent::query()
            ->where('to_status', 'terminated')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->count();

        return [
            'active_at_period_start' => $activeAtPeriodStart,
            'terminated_services' => $terminated,
            'retention_rate_percent' => $activeAtPeriodStart === 0 ? null : round(max(0, $activeAtPeriodStart - $terminated) / $activeAtPeriodStart * 100, 2),
        ];
    }

    /** @param Collection<int, Payment> $payments @return list<array{collector: string, payment_count: int, totals_by_currency: array<string, int>}> */
    private function collectorPerformance(Collection $payments): array
    {
        /** @var array<string, array{collector: string, payment_count: int, totals_by_currency: array<string, int>}> $performance */
        $performance = [];
        foreach ($payments as $payment) {
            $collector = $payment->cashShift?->user;
            $key = $collector === null ? 'unassigned' : 'user:'.$collector->id;
            $performance[$key] ??= ['collector' => $collector === null ? 'Unassigned' : $collector->name, 'payment_count' => 0, 'totals_by_currency' => []];
            $performance[$key]['payment_count']++;
            $performance[$key]['totals_by_currency'][$payment->currency] = ($performance[$key]['totals_by_currency'][$payment->currency] ?? 0) + $payment->amount;
        }

        return array_values($performance);
    }

    /** @return list<array{service_id: string|null, username: string|null, total_octets: int}> */
    private function topUsage(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $usage = UsageDaily::query()
            ->whereBetween('usage_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('service_id, SUM(total_octets) as total_octets')
            ->groupBy('service_id')
            ->orderByDesc('total_octets')
            ->limit(10)
            ->get();
        $services = Service::query()->whereIn('id', $usage->pluck('service_id'))->get()->keyBy('id');

        return $usage->map(fn ($row): array => [
            'service_id' => $services->get($row->service_id)?->public_id,
            'username' => $services->get($row->service_id)?->username,
            'total_octets' => (int) $row->total_octets,
        ])->values()->all();
    }

    /** @return array{aging_by_currency: array<string, array<string, int>>, outstanding_by_currency: array<string, int>} */
    private function aging(CarbonImmutable $asOf): array
    {
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->where('issued_at', '<=', $asOf->endOfDay())
            ->get(['id', 'currency', 'total_amount', 'due_at']);
        $allocated = PaymentAllocation::query()
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->where('created_at', '<=', $asOf->endOfDay())
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id')
            ->map(fn ($value): int => (int) $value);
        $credited = CreditNote::query()
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->where('status', 'issued')
            ->where('issued_at', '<=', $asOf->endOfDay())
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id')
            ->map(fn ($value): int => (int) $value);
        $buckets = [];
        $outstanding = [];

        foreach ($invoices as $invoice) {
            $amount = max(0, $invoice->total_amount - (int) ($allocated[$invoice->id] ?? 0) - (int) ($credited[$invoice->id] ?? 0));
            if ($amount === 0) {
                continue;
            }
            $currency = $invoice->currency;
            $bucket = $this->bucket($invoice->due_at, $asOf);
            $buckets[$currency] ??= ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
            $buckets[$currency][$bucket] += $amount;
            $outstanding[$currency] = ($outstanding[$currency] ?? 0) + $amount;
        }

        return ['aging_by_currency' => $buckets, 'outstanding_by_currency' => $outstanding];
    }

    private function bucket(?Carbon $dueAt, CarbonImmutable $asOf): string
    {
        if ($dueAt === null || $dueAt->startOfDay()->greaterThanOrEqualTo($asOf->startOfDay())) {
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
