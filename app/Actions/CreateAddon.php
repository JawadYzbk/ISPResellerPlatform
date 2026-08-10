<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Addon;
use Illuminate\Support\Str;

final readonly class CreateAddon implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Addon
    {
        return Addon::create([
            'name' => $data['name'],
            'slug' => Str::slug((string) ($data['slug'] ?: $data['name'])),
            'description' => $data['description'] ?? null,
            'amount_minor' => $data['amount_minor'],
            'currency' => strtoupper((string) $data['currency']),
            'billing_period_days' => $data['billing_period_days'] ?? null,
            'status' => $data['status'],
        ]);
    }
}
