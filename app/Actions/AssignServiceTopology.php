<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\ServiceStatus;
use App\Models\DistributionBox;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AssignServiceTopology implements Action
{
    public function handle(Service $service, DistributionBox $box, int $port, ?User $actor = null): Service
    {
        return DB::transaction(function () use ($service, $box, $port, $actor): Service {
            $lockedService = Service::query()->lockForUpdate()->findOrFail($service->id);
            $lockedBox = DistributionBox::query()->lockForUpdate()->findOrFail($box->id);

            if ($lockedService->tenant_id !== $lockedBox->tenant_id || ($actor !== null && $actor->tenant_id !== $lockedService->tenant_id)) {
                throw new DomainException('The service, distribution box, and actor must belong to the same tenant.');
            }
            if ($lockedService->status === ServiceStatus::Terminated) {
                throw new DomainException('Terminated services cannot receive a network assignment.');
            }
            if ($lockedBox->status !== 'active') {
                throw new DomainException('Only active distribution boxes can receive service assignments.');
            }
            if ($port < 1 || $port > $lockedBox->capacity_ports) {
                throw new DomainException("Port {$port} is outside the box capacity.");
            }

            $occupied = Service::query()
                ->where('distribution_box_id', $lockedBox->id)
                ->where('network_port', $port)
                ->whereKeyNot($lockedService->id)
                ->exists();
            if ($occupied) {
                throw new DomainException("Port {$port} is already assigned in {$lockedBox->code}.");
            }

            $previous = [
                'building_id' => $lockedService->network_building_id,
                'box_id' => $lockedService->distribution_box_id,
                'port' => $lockedService->network_port,
            ];
            $lockedService->forceFill([
                'network_building_id' => $lockedBox->network_building_id,
                'distribution_box_id' => $lockedBox->id,
                'network_port' => $port,
            ])->save();

            ServiceEvent::create([
                'service_id' => $lockedService->id,
                'actor_id' => $actor?->id,
                'event_type' => 'topology_assigned',
                'metadata' => [
                    'previous' => $previous,
                    'building_id' => $lockedBox->network_building_id,
                    'box_id' => $lockedBox->id,
                    'box_code' => $lockedBox->code,
                    'port' => $port,
                ],
            ]);

            return $lockedService->refresh();
        });
    }
}
