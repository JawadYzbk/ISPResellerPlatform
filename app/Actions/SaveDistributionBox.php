<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\DistributionBox;
use App\Models\NetworkBuilding;
use DomainException;

final readonly class SaveDistributionBox implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(NetworkBuilding $building, array $data, ?DistributionBox $box = null): DistributionBox
    {
        $box ??= new DistributionBox;

        if ($box->exists && ($box->tenant_id !== $building->tenant_id || $box->network_building_id !== $building->id)) {
            throw new DomainException('The distribution box must belong to the selected building.');
        }

        $capacity = (int) $data['capacity_ports'];
        if ($box->exists && $capacity < $box->usedPorts()) {
            throw new DomainException('Capacity cannot be lower than the number of assigned service ports.');
        }

        $box->forceFill([
            'tenant_id' => $building->tenant_id,
            'network_building_id' => $building->id,
            'pop_id' => $data['pop_id'] ?? null,
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'box_type' => $data['box_type'],
            'capacity_ports' => $capacity,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'status' => $data['status'],
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ])->save();

        return $box->refresh();
    }
}
