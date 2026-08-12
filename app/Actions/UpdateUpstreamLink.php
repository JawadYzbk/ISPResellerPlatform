<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\UpstreamLink;

final readonly class UpdateUpstreamLink implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(UpstreamLink $link, array $data): UpstreamLink
    {
        $link->update([
            'provider_name' => trim((string) $data['provider_name']),
            'capacity_mbps' => filled($data['capacity_mbps'] ?? null) ? (int) $data['capacity_mbps'] : null,
            'monthly_cost_amount' => (int) $data['monthly_cost_amount'],
            'currency' => strtoupper((string) $data['currency']),
            'contract_start' => $data['contract_start'],
            'contract_end' => $data['contract_end'] ?? null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);

        return $link->refresh();
    }
}
