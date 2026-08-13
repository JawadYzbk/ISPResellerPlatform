<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\NetworkBuilding;
use App\Models\Tenant;
use DomainException;

final readonly class SaveNetworkBuilding implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Tenant $tenant, array $data, ?NetworkBuilding $building = null): NetworkBuilding
    {
        $building ??= new NetworkBuilding;

        if ($building->exists && $building->tenant_id !== $tenant->id) {
            throw new DomainException('The building must belong to the active tenant.');
        }

        $building->forceFill([
            'tenant_id' => $tenant->id,
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'floors' => filled($data['floors'] ?? null) ? (int) $data['floors'] : null,
            'unit_count' => filled($data['unit_count'] ?? null) ? (int) $data['unit_count'] : null,
            'status' => $data['status'],
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ])->save();

        return $building->refresh();
    }
}
