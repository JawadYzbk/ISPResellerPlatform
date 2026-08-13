<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorZoneAssignment;
use App\Models\User;
use App\Models\Zone;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCollectorTerritories implements Action
{
    /** @param array{all_zones: bool, zone_ids?: list<int>} $data */
    public function handle(User $actor, User $collector, array $data): User
    {
        if ($actor->tenant_id === null || $actor->tenant_id !== $collector->tenant_id) {
            throw new DomainException('The collector does not belong to this workspace.');
        }

        if (! $actor->can('users.manage')) {
            throw new DomainException('You are not allowed to manage collector territories.');
        }

        if ($collector->role !== 'collector') {
            throw new DomainException('Territories can only be assigned to collector accounts.');
        }

        $allZones = (bool) $data['all_zones'];
        $zoneIds = $allZones ? [] : array_values(array_unique(array_map('intval', $data['zone_ids'] ?? [])));
        if (! $allZones && $zoneIds === []) {
            throw new DomainException('Choose at least one service zone or allow all zones.');
        }

        if (Zone::query()->whereIn('id', $zoneIds)->count() !== count($zoneIds)) {
            throw new DomainException('One or more selected zones are not available in this workspace.');
        }

        return DB::transaction(function () use ($actor, $collector, $allZones, $zoneIds): User {
            $lockedCollector = User::query()->lockForUpdate()->findOrFail($collector->id);
            if ($lockedCollector->tenant_id !== $actor->tenant_id || $lockedCollector->role !== 'collector') {
                throw new DomainException('The collector account changed while its territory was being saved.');
            }

            $now = now();
            $active = CollectorZoneAssignment::query()
                ->where('user_id', $lockedCollector->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->get()
                ->keyBy('zone_id');

            foreach ($active as $zoneId => $assignment) {
                if ($allZones || ! in_array((int) $zoneId, $zoneIds, true)) {
                    $assignment->update(['ended_at' => $now, 'ended_by_id' => $actor->id]);
                }
            }

            if (! $allZones) {
                foreach ($zoneIds as $zoneId) {
                    if (! $active->has($zoneId)) {
                        CollectorZoneAssignment::create([
                            'user_id' => $lockedCollector->id,
                            'zone_id' => $zoneId,
                            'assigned_by_id' => $actor->id,
                            'started_at' => $now,
                        ]);
                    }
                }
            }

            $lockedCollector->forceFill(['collector_all_zones' => $allZones])->save();

            return $lockedCollector->refresh();
        });
    }
}
