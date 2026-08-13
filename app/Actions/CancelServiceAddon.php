<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ServiceAddon;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CancelServiceAddon implements Action
{
    public function handle(ServiceAddon $serviceAddon, User $actor, ?CarbonImmutable $endsAt = null): ServiceAddon
    {
        if ((int) $serviceAddon->tenant_id !== (int) $actor->tenant_id) {
            throw new DomainException('The add-on and operator must belong to the same tenant.');
        }
        if ($serviceAddon->status !== 'active') {
            throw new DomainException('This add-on is already inactive.');
        }

        return DB::transaction(function () use ($serviceAddon, $actor, $endsAt): ServiceAddon {
            $locked = ServiceAddon::query()->lockForUpdate()->findOrFail($serviceAddon->id);
            $effectiveEnd = $endsAt ?? CarbonImmutable::today();
            $metadata = $locked->metadata ?? [];
            $metadata['cancelled_by_id'] = $actor->id;
            $metadata['cancelled_at'] = now()->toIso8601String();
            $locked->forceFill([
                'status' => 'cancelled',
                'ends_at' => $effectiveEnd->toDateString(),
                'metadata' => $metadata,
            ])->save();

            return $locked->refresh()->load('addon');
        });
    }
}
