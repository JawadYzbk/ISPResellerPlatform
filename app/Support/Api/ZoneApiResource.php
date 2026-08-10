<?php

namespace App\Support\Api;

use App\Models\Zone;

final readonly class ZoneApiResource
{
    /** @return array<string, mixed> */
    public function make(Zone $zone): array
    {
        $zone->loadMissing('parent');
        $zone->loadCount('customers');

        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'code' => $zone->code,
            'parent' => $zone->parent === null ? null : [
                'id' => $zone->parent->id,
                'name' => $zone->parent->name,
                'code' => $zone->parent->code,
            ],
            'customers_count' => $zone->customers_count,
        ];
    }
}
