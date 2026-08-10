<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Addon;
use Illuminate\Support\Str;

final readonly class UpdateAddon implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Addon $addon, array $data): Addon
    {
        $addon->forceFill([
            'name' => $data['name'],
            'slug' => Str::slug((string) ($data['slug'] ?: $data['name'])),
            'description' => $data['description'] ?? null,
            'amount_minor' => $data['amount_minor'],
            'currency' => strtoupper((string) $data['currency']),
            'billing_period_days' => $data['billing_period_days'] ?? null,
            'status' => $data['status'],
        ])->save();

        return $addon->refresh();
    }
}
