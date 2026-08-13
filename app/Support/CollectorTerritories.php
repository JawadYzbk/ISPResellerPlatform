<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;

final class CollectorTerritories
{
    /** @var array<int, list<int>|null> */
    private array $resolved = [];

    /**
     * Null means the collector can cover every zone. An empty list means no
     * customer is currently assigned.
     *
     * @return list<int>|null
     */
    public function zoneIds(User $collector): ?array
    {
        if (array_key_exists($collector->id, $this->resolved)) {
            return $this->resolved[$collector->id];
        }

        if ($collector->getAttribute('collector_all_zones') === null || (bool) $collector->collector_all_zones) {
            return $this->resolved[$collector->id] = null;
        }

        $roots = $collector->activeCollectorZoneAssignments()
            ->pluck('zone_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($roots === []) {
            return $this->resolved[$collector->id] = [];
        }

        $children = [];
        foreach (Zone::query()->get(['id', 'parent_id']) as $zone) {
            if ($zone->parent_id !== null) {
                $children[(int) $zone->parent_id][] = (int) $zone->id;
            }
        }

        $resolved = array_fill_keys($roots, true);
        $pending = $roots;
        while (($parentId = array_shift($pending)) !== null) {
            foreach ($children[$parentId] ?? [] as $childId) {
                if (! isset($resolved[$childId])) {
                    $resolved[$childId] = true;
                    $pending[] = $childId;
                }
            }
        }

        return $this->resolved[$collector->id] = array_map('intval', array_keys($resolved));
    }

    /** @param Builder<Customer> $query */
    public function constrainCustomers(Builder $query, User $collector): Builder
    {
        $zoneIds = $this->zoneIds($collector);

        return $zoneIds === null ? $query : $query->whereIn('zone_id', $zoneIds);
    }

    public function allowsCustomer(User $collector, Customer $customer): bool
    {
        $zoneIds = $this->zoneIds($collector);

        return $zoneIds === null || ($customer->zone_id !== null && in_array((int) $customer->zone_id, $zoneIds, true));
    }
}
