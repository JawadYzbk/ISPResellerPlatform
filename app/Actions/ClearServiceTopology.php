<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ClearServiceTopology implements Action
{
    public function handle(Service $service, ?User $actor = null): Service
    {
        return DB::transaction(function () use ($service, $actor): Service {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            if ($actor !== null && $actor->tenant_id !== $locked->tenant_id) {
                throw new DomainException('The actor must belong to the service tenant.');
            }

            $previous = [
                'building_id' => $locked->network_building_id,
                'box_id' => $locked->distribution_box_id,
                'port' => $locked->network_port,
            ];
            if ($previous['building_id'] === null && $previous['box_id'] === null && $previous['port'] === null) {
                return $locked;
            }

            $locked->forceFill([
                'network_building_id' => null,
                'distribution_box_id' => null,
                'network_port' => null,
            ])->save();

            ServiceEvent::create([
                'service_id' => $locked->id,
                'actor_id' => $actor?->id,
                'event_type' => 'topology_unassigned',
                'metadata' => ['previous' => $previous],
            ]);

            return $locked->refresh();
        });
    }
}
