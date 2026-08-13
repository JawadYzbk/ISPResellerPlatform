<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Plan;
use App\Models\PlanUsageRate;

final readonly class CreatePlanUsageRate implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Plan $plan, array $data): PlanUsageRate
    {
        return PlanUsageRate::create([
            'plan_id' => $plan->id,
            'name' => $data['name'],
            'metric' => $data['metric'],
            'included_bytes' => $data['included_bytes'],
            'unit_bytes' => $data['unit_bytes'],
            'amount_minor' => $data['amount_minor'],
            'currency' => strtoupper((string) $data['currency']),
            'rounding' => $data['rounding'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'status' => $data['status'],
        ]);
    }
}
