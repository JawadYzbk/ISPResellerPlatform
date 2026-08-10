<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Plan;
use App\Models\Service;
use Carbon\CarbonImmutable;
use DomainException;

final readonly class PreviewServicePlanChange implements Action
{
    /** @return array<string, mixed> */
    public function handle(Service $service, Plan $newPlan, string $effective): array
    {
        if (! in_array($effective, ['immediate', 'next_cycle'], true)) {
            throw new DomainException('Plan changes must be immediate or scheduled for the next cycle.');
        }

        $service->loadMissing(['plan', 'customer', 'tenant']);
        if ($newPlan->tenant_id !== $service->tenant_id || $newPlan->status !== 'active' || $newPlan->id === $service->plan_id) {
            throw new DomainException('The selected plan is not available for this service.');
        }
        if ($service->status->value === 'terminated') {
            throw new DomainException('Terminated services cannot change plan.');
        }

        $now = CarbonImmutable::now($service->tenant->timezone);
        $periodStart = $service->activated_at === null ? $now : CarbonImmutable::instance($service->activated_at);
        $periodEnd = $service->expires_at === null ? $now : CarbonImmutable::instance($service->expires_at);
        $periodSeconds = max(1, $periodEnd->diffInSeconds($periodStart, true));
        $remainingSeconds = max(0, $now->diffInSeconds($periodEnd, false));

        if ($effective === 'next_cycle') {
            return [
                'effective' => $effective,
                'apply_at' => $service->expires_at?->toIso8601String(),
                'from_plan_id' => $service->plan->public_id,
                'to_plan_id' => $newPlan->public_id,
                'currency' => $service->plan->currency,
                'old_credit_amount' => 0,
                'new_charge_amount' => 0,
                'net_amount' => 0,
                'remaining_seconds' => $remainingSeconds,
            ];
        }

        $oldPrice = $service->plan->priceAt($now);
        $newPrice = $newPlan->priceAt($now);
        if ($oldPrice === null || $newPrice === null) {
            throw new DomainException('Both plans need a current price before an immediate change.');
        }
        if ($oldPrice->currency !== $newPrice->currency || $oldPrice->currency !== $service->customer->balance_currency) {
            throw new DomainException('Immediate plan changes require both prices to use the customer ledger currency.');
        }

        $oldCredit = intdiv(($oldPrice->amount_minor * $remainingSeconds) + intdiv($periodSeconds, 2), $periodSeconds);
        $newCharge = intdiv(($newPrice->amount_minor * $remainingSeconds) + intdiv($periodSeconds, 2), $periodSeconds);

        return [
            'effective' => $effective,
            'apply_at' => $now->toIso8601String(),
            'from_plan_id' => $service->plan->public_id,
            'to_plan_id' => $newPlan->public_id,
            'currency' => $newPrice->currency,
            'old_credit_amount' => $oldCredit,
            'new_charge_amount' => $newCharge,
            'net_amount' => $newCharge - $oldCredit,
            'remaining_seconds' => $remainingSeconds,
        ];
    }
}
