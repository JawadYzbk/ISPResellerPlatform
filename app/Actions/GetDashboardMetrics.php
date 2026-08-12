<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\IncidentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\NetworkState;
use App\Enums\PaymentStatus;
use App\Enums\ServiceStatus;
use App\Models\CreditNote;
use App\Models\CurrentSession;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\NetworkCommand;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\Tenant;
use App\Models\UpstreamLink;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

final readonly class GetDashboardMetrics implements Action
{
    /** @return array<string, mixed> */
    public function handle(?User $user = null): array
    {
        $metrics = [
            'customers' => Customer::count(),
            'activeServices' => Service::where('status', ServiceStatus::Active)->count(),
            'attention' => Service::whereIn('status', [ServiceStatus::Suspended, ServiceStatus::Pending])->count(),
            'expiringSoon' => Service::whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
            'collectionsTodayByCurrency' => Payment::query()
                ->where('status', PaymentStatus::Posted)
                ->whereDate('received_at', today())
                ->selectRaw('currency, SUM(amount) as total')
                ->groupBy('currency')
                ->orderBy('currency')
                ->pluck('total', 'currency')
                ->map(fn (mixed $value): int => (int) $value)
                ->all(),
            'networkPending' => NetworkCommand::whereIn('status', ['pending', 'running', 'failed', 'awaiting_confirmation'])->count(),
            'failedCommands' => NetworkCommand::where('status', 'failed')->count(),
            'offlineRouters' => Router::where('status', 'offline')->count(),
            'activeSessions' => CurrentSession::whereNull('stopped_at')->count(),
            'driftedServices' => Service::whereIn('network_state', [NetworkState::Drifted, NetworkState::Failed])->count(),
            'openIncidents' => Incident::where('status', IncidentStatus::Open)->count(),
            'openWorkOrders' => WorkOrder::whereNotIn('status', ['completed', 'cancelled'])->count(),
        ];

        $metrics['owner'] = $user !== null && ! $user->can('reports.finance')
            ? null
            : $this->ownerMetrics();

        return $metrics;
    }

    /** @return array<string, mixed> */
    private function ownerMetrics(): array
    {
        $tenant = Tenant::query()->findOrFail(app(Tenancy::class)->requireId());
        $periodStart = CarbonImmutable::now()->startOfMonth();
        $periodEnd = CarbonImmutable::now();
        $report = $this->financeSnapshot($periodStart, $periodEnd);
        /** @var array<string, int> $marginByCurrency */
        $marginByCurrency = $report['margin_by_currency'];

        /** @var array<string, int> $invoicedByCurrency */
        $invoicedByCurrency = $report['invoiced_by_currency'];
        /** @var array<string, int> $collectedByCurrency */
        $collectedByCurrency = $report['collected_by_currency'];
        /** @var array<string, float|null> $collectionRates */
        $collectionRates = $report['collection_rate_by_currency'];
        $currencies = array_unique([
            ...array_keys($invoicedByCurrency),
            ...array_keys($collectedByCurrency),
            ...array_keys($marginByCurrency),
        ]);
        $currencyMetrics = [];

        foreach ($currencies as $currency) {
            $currencyMetrics[$currency] = [
                'revenue' => $invoicedByCurrency[$currency] ?? 0,
                'collected' => $collectedByCurrency[$currency] ?? 0,
                'collectionRate' => $collectionRates[$currency] ?? null,
                'margin' => $marginByCurrency[$currency] ?? 0,
            ];
        }

        $baseCurrency = strtoupper((string) $tenant->base_currency);

        return [
            'period' => ['from' => $periodStart->toDateString(), 'to' => $periodEnd->toDateString()],
            'baseCurrency' => $baseCurrency,
            'revenue' => $currencyMetrics[$baseCurrency]['revenue'] ?? 0,
            'collected' => $currencyMetrics[$baseCurrency]['collected'] ?? 0,
            'collectionRate' => $currencyMetrics[$baseCurrency]['collectionRate'] ?? null,
            'margin' => $currencyMetrics[$baseCurrency]['margin'] ?? 0,
            'currencyMetrics' => $currencyMetrics,
            'statusTrend' => $this->statusTrend($periodEnd),
        ];
    }

    /** @return array{invoiced_by_currency: array<string, int>, collected_by_currency: array<string, int>, collection_rate_by_currency: array<string, float|null>, margin_by_currency: array<string, int>} */
    private function financeSnapshot(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $invoiced = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('currency, SUM(total_amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
        $credits = CreditNote::query()
            ->where('status', 'issued')
            ->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
        $invoicedByCurrency = [];

        foreach (array_unique([...array_keys($invoiced), ...array_keys($credits)]) as $currency) {
            $invoicedByCurrency[$currency] = max(0, ($invoiced[$currency] ?? 0) - ($credits[$currency] ?? 0));
        }

        $collectedByCurrency = Payment::query()
            ->where('status', PaymentStatus::Posted)
            ->whereBetween('received_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
        $collectionRates = [];

        foreach (array_unique([...array_keys($invoicedByCurrency), ...array_keys($collectedByCurrency)]) as $currency) {
            $revenue = $invoicedByCurrency[$currency] ?? 0;
            $collected = $collectedByCurrency[$currency] ?? 0;
            $collectionRates[$currency] = $revenue === 0 ? null : round(($collected / $revenue) * 100, 2);
        }

        $marginByCurrency = InvoiceLine::query()
            ->whereHas('invoice', fn ($query) => $query
                ->where('status', InvoiceStatus::Issued)
                ->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()]))
            ->selectRaw('invoice_lines.currency, SUM(invoice_lines.total_amount) as total')
            ->groupBy('invoice_lines.currency')
            ->pluck('total', 'currency')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();

        foreach ($this->upstreamCosts($from, $to) as $currency => $amount) {
            $marginByCurrency[$currency] = ($marginByCurrency[$currency] ?? 0) - $amount;
        }

        return [
            'invoiced_by_currency' => $invoicedByCurrency,
            'collected_by_currency' => $collectedByCurrency,
            'collection_rate_by_currency' => $collectionRates,
            'margin_by_currency' => $marginByCurrency,
        ];
    }

    /** @return array<string, int> */
    private function upstreamCosts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $costs = [];
        UpstreamLink::query()
            ->whereDate('contract_start', '<=', $to->toDateString())
            ->where(fn ($query) => $query->whereNull('contract_end')->orWhereDate('contract_end', '>=', $from->toDateString()))
            ->get(['monthly_cost_amount', 'currency', 'contract_start', 'contract_end'])
            ->each(function (UpstreamLink $link) use (&$costs, $from, $to): void {
                $start = CarbonImmutable::parse($link->contract_start)->max($from->startOfDay());
                $end = CarbonImmutable::parse($link->contract_end ?? $to->toDateString())->min($to->endOfDay());
                if ($end->lessThan($start)) {
                    return;
                }

                $costs[$link->currency] = ($costs[$link->currency] ?? 0) + $this->proratedMonthlyCost($link->monthly_cost_amount, $start, $end);
            });

        return $costs;
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

    /** @return list<array{month: string, active: int, suspended: int}> */
    private function statusTrend(CarbonImmutable $periodEnd): array
    {
        $firstMonth = $periodEnd->startOfMonth()->subMonths(5);
        $services = Service::query()->get(['id', 'status', 'created_at'])->keyBy('id');
        $events = ServiceEvent::query()
            ->where('created_at', '<=', $periodEnd->endOfDay())
            ->where('created_at', '>', $firstMonth->endOfMonth())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['service_id', 'from_status', 'created_at'])
            ->values();
        $statuses = $services->mapWithKeys(fn (Service $service): array => [$service->id => $service->status->value])->all();
        $monthEnds = [];

        for ($month = $firstMonth; $month->lessThanOrEqualTo($periodEnd); $month = $month->addMonth()) {
            $monthEnds[] = $month->endOfMonth();
        }

        $trend = [];
        $eventIndex = 0;

        for ($monthIndex = count($monthEnds) - 1; $monthIndex >= 0; $monthIndex--) {
            $monthEnd = $monthEnds[$monthIndex];
            if ($monthIndex < count($monthEnds) - 1) {
                while ($eventIndex < $events->count() && $events[$eventIndex]->created_at->greaterThan($monthEnd)) {
                    $event = $events[$eventIndex];
                    $statuses[$event->service_id] = (string) ($event->from_status ?: $statuses[$event->service_id]);
                    $eventIndex++;
                }
            }

            $active = 0;
            $suspended = 0;

            foreach ($services as $service) {
                if ($service->created_at === null || $service->created_at->greaterThan($monthEnd)) {
                    continue;
                }

                $active += $statuses[$service->id] === ServiceStatus::Active->value ? 1 : 0;
                $suspended += $statuses[$service->id] === ServiceStatus::Suspended->value ? 1 : 0;
            }

            $trend[] = ['month' => $monthEnd->format('M Y'), 'active' => $active, 'suspended' => $suspended];
        }

        return array_reverse($trend);
    }
}
