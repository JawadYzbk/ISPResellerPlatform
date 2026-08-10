<?php

namespace App\Support\Api;

use App\Models\Plan;
use App\Models\PlanPrice;

final class PlanApiResource
{
    /** @return array<string, mixed> */
    public function make(Plan $plan): array
    {
        $plan->loadMissing('prices');
        $price = $plan->prices
            ->filter(fn (PlanPrice $candidate): bool => $candidate->isEffectiveAt(now()))
            ->sortByDesc('effective_from')
            ->first();

        return [
            'id' => $plan->public_id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'status' => $plan->status,
            'download_kbps' => $plan->download_kbps,
            'upload_kbps' => $plan->upload_kbps,
            'duration_days' => $plan->duration_days,
            'amount_minor' => $plan->amount_minor,
            'currency' => $plan->currency,
            'services_count' => $plan->services_count ?? null,
            'current_price' => $price === null ? null : [
                'amount_minor' => $price->amount_minor,
                'currency' => $price->currency,
                'effective_from' => $price->effective_from->toIso8601String(),
            ],
        ];
    }
}
