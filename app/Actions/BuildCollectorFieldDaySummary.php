<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\PaymentStatus;
use App\Models\CashShift;
use App\Models\CollectorFieldDay;
use App\Models\CollectorRoute;
use App\Models\CollectorTask;
use App\Models\Payment;

final readonly class BuildCollectorFieldDaySummary implements Action
{
    public function __construct(private GetCollectorCustodyPosition $custodyPosition) {}

    /** @return array<string, mixed> */
    public function handle(CollectorFieldDay $fieldDay): array
    {
        $end = $fieldDay->checked_out_at ?? now();
        $payments = Payment::query()
            ->where('actor_id', $fieldDay->user_id)
            ->where('status', PaymentStatus::Posted)
            ->whereBetween('received_at', [$fieldDay->checked_in_at, $end])
            ->get(['amount', 'currency']);
        $totals = [];
        foreach ($payments as $payment) {
            $totals[$payment->currency] = ($totals[$payment->currency] ?? 0) + $payment->amount;
        }
        ksort($totals);

        $timezone = $fieldDay->tenant()->value('timezone') ?: 'UTC';
        $routeDate = $fieldDay->checked_in_at->copy()->setTimezone($timezone)->toDateString();
        $route = CollectorRoute::query()
            ->where('user_id', $fieldDay->user_id)
            ->whereDate('route_date', $routeDate)
            ->with('stops:id,collector_route_id,outcome')
            ->first();
        $outcomes = [];
        $stops = $route === null ? collect() : $route->stops;
        foreach ($stops as $stop) {
            $outcomes[$stop->outcome] = ($outcomes[$stop->outcome] ?? 0) + 1;
        }

        $completedTasks = CollectorTask::query()
            ->where('collector_id', $fieldDay->user_id)
            ->whereBetween('completed_at', [$fieldDay->checked_in_at, $end])
            ->count();
        $openTasks = CollectorTask::query()
            ->where('collector_id', $fieldDay->user_id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
        $cashShift = CashShift::query()
            ->where('user_id', $fieldDay->user_id)
            ->where('opened_at', '<=', $end)
            ->where(fn ($query) => $query->whereNull('closed_at')->orWhere('closed_at', '>=', $fieldDay->checked_in_at))
            ->latest('opened_at')
            ->first();

        return [
            'duration_minutes' => (int) $fieldDay->checked_in_at->diffInMinutes($end),
            'payments' => ['count' => $payments->count(), 'totals' => $totals],
            'route' => [
                'status' => $route?->status,
                'stops' => $route?->stops->count() ?? 0,
                'completed' => $route?->stops->where('outcome', '!=', 'pending')->count() ?? 0,
                'outcomes' => $outcomes,
            ],
            'tasks' => ['completed' => $completedTasks, 'open' => $openTasks],
            'cash_shift' => $cashShift === null ? null : [
                'id' => $cashShift->public_id,
                'status' => $cashShift->status->value,
                'system_totals' => $cashShift->system_totals ?? [],
                'variance' => $cashShift->variance,
            ],
            'custody' => $this->custodyPosition->handle($fieldDay->collector),
        ];
    }
}
