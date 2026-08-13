<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CancelServicePlanChange implements Action
{
    public function handle(Service $service, ?User $actor = null): Service
    {
        return DB::transaction(function () use ($service, $actor): Service {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            $metadata = $locked->metadata ?? [];
            $pending = $metadata['pending_plan_change'] ?? null;
            if (! is_array($pending)) {
                throw new DomainException('This service has no scheduled plan change to cancel.');
            }

            unset($metadata['pending_plan_change']);
            $locked->forceFill(['metadata' => $metadata])->save();
            ServiceEvent::create([
                'service_id' => $locked->id,
                'actor_id' => $actor?->id,
                'event_type' => 'plan_change_cancelled',
                'metadata' => [
                    'to_plan_id' => $pending['plan_id'] ?? null,
                    'effective' => 'next_cycle',
                ],
            ]);

            return $locked->refresh();
        });
    }
}
