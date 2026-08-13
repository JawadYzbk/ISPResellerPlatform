<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\PlanUsageRate;

final readonly class UpdatePlanUsageRate implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(PlanUsageRate $rate, array $data): PlanUsageRate
    {
        $rate->forceFill([
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
        ])->save();

        return $rate->refresh();
    }
}
