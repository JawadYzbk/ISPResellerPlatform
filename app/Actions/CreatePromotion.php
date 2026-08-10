<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Plan;
use App\Models\Promotion;

final readonly class CreatePromotion implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Promotion
    {
        $planIds = Plan::query()->whereIn('public_id', $data['applies_to'] ?? [])->pluck('public_id')->values()->all();

        return Promotion::create([
            'name' => $data['name'],
            'code' => strtoupper((string) $data['code']),
            'type' => $data['type'],
            'value' => $data['value'],
            'applies_to' => $planIds === [] ? null : $planIds,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'is_active' => $data['is_active'],
        ]);
    }
}
