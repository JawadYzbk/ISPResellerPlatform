<?php

namespace App\Domain\Services;

use App\Actions\ReturnServiceInventory;
use App\Domain\Radius\RadiusSyncService;
use App\Enums\NetworkState;
use App\Enums\ProvisioningMode;
use App\Enums\ServiceStatus;
use App\Events\ServiceStatusChanged;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ServiceStateMachine
{
    public function __construct(private readonly ReturnServiceInventory $returnServiceInventory) {}

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['active', 'suspended', 'paused', 'terminated'],
        'active' => ['suspended', 'paused', 'terminated'],
        'suspended' => ['active', 'terminated'],
        'paused' => ['active', 'terminated'],
        'terminated' => [],
    ];

    /** @param array<string, mixed> $metadata */
    public function transition(Service $service, ServiceStatus $target, ?User $actor = null, array $metadata = [], bool $explicitReactivation = false): Service
    {
        return DB::transaction(function () use ($service, $target, $actor, $metadata, $explicitReactivation): Service {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            $from = $locked->status;

            if ($from === $target) {
                return $locked;
            }

            $allowed = self::TRANSITIONS[$from->value] ?? [];
            if (! in_array($target->value, $allowed, true) && ! ($from === ServiceStatus::Terminated && $target === ServiceStatus::Active && $explicitReactivation)) {
                throw new DomainException("Service transition {$from->value} -> {$target->value} is not allowed.");
            }

            $locked->forceFill([
                'status' => $target,
                'network_state' => NetworkState::PendingSync,
                'desired_state_version' => $locked->desired_state_version + 1,
                'activated_at' => $target === ServiceStatus::Active ? ($locked->activated_at ?? now()) : $locked->activated_at,
                'suspension_reason' => in_array($target, [ServiceStatus::Suspended, ServiceStatus::Paused], true) ? ($metadata['reason'] ?? $locked->suspension_reason) : null,
                'paused_until' => $target === ServiceStatus::Paused ? ($metadata['resume_at'] ?? null) : null,
            ])->save();

            ServiceEvent::create([
                'service_id' => $locked->id,
                'actor_id' => $actor?->id,
                'event_type' => $from === ServiceStatus::Terminated ? 'reactivated' : 'status_changed',
                'from_status' => $from->value,
                'to_status' => $target->value,
                'metadata' => $metadata,
            ]);
            if ($target === ServiceStatus::Terminated) {
                $this->returnServiceInventory->handle($locked, $actor);
            }
            $locked->loadMissing('tenant');
            event(new ServiceStatusChanged($locked->tenant->public_id, $locked->public_id, $from->value, $target->value));

            if ($locked->provisioning_mode === ProvisioningMode::Radius) {
                app(RadiusSyncService::class)->sync($locked);
            }

            return $locked->refresh();
        });
    }
}
