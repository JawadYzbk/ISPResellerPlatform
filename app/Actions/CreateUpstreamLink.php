<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Pop;
use App\Models\UpstreamLink;

final readonly class CreateUpstreamLink implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Pop $pop, array $data): UpstreamLink
    {
        return $pop->upstreamLinks()->create([
            'provider_name' => trim((string) $data['provider_name']),
            'capacity_mbps' => $data['capacity_mbps'] ?? null,
            'monthly_cost_amount' => $data['monthly_cost_amount'],
            'currency' => strtoupper((string) $data['currency']),
            'contract_start' => $data['contract_start'],
            'contract_end' => $data['contract_end'] ?? null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);
    }
}
