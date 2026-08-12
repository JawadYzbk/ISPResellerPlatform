<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Tenant;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

final readonly class SaveZoneLocation implements Action
{
    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, array $attributes, ?Zone $zone = null): Zone
    {
        return DB::transaction(function () use ($tenant, $attributes, $zone): Zone {
            if (! $zone instanceof Zone) {
                $created = $tenant->zones()->create($attributes);

                return $created instanceof Zone ? $created : throw new \LogicException('Zone creation returned an unexpected model.');
            }

            $zone->update($attributes);

            return $zone->refresh();
        });
    }
}
