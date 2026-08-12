<?php

namespace App\Domain\Network;

use App\Enums\CredentialStatus;
use App\Models\CredentialAssignment;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\UpstreamCredential;
use Illuminate\Support\Facades\DB;

final class CredentialDriver implements NetworkDriver
{
    public function execute(Service $service, NetworkCommand $command): DriverResult
    {
        return match ($command->action) {
            'activate' => $this->activate($service),
            'suspend', 'pause' => $this->release($service, $command->action),
            'disconnect' => $this->disconnect($service, $command),
            'change_plan', 'throttle' => DriverResult::success('Upstream credential does not require a plan sync.', [
                'action' => $command->action,
            ]),
            default => DriverResult::failure('unsupported_credential_action: '.$command->action),
        };
    }

    private function activate(Service $service): DriverResult
    {
        return DB::transaction(function () use ($service): DriverResult {
            $lockedService = Service::query()->lockForUpdate()->find($service->id);
            if (! $lockedService instanceof Service) {
                return DriverResult::failure('service_not_found');
            }

            $assignment = CredentialAssignment::query()
                ->where('service_id', $lockedService->id)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->first();

            $credential = $assignment instanceof CredentialAssignment
                ? UpstreamCredential::query()->lockForUpdate()->find($assignment->upstream_credential_id)
                : UpstreamCredential::query()
                    ->where('status', CredentialStatus::Available)
                    ->where(function ($query): void {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

            if (! $credential instanceof UpstreamCredential) {
                return DriverResult::failure('no_available_upstream_credential');
            }

            if ($credential->expires_at !== null && $credential->expires_at->isPast()) {
                $credential->forceFill(['status' => CredentialStatus::Expired])->save();

                return DriverResult::failure('upstream_credential_expired');
            }

            if ($credential->status === CredentialStatus::Revoked || $credential->status === CredentialStatus::Invalid) {
                return DriverResult::failure('upstream_credential_unusable');
            }

            $now = now();
            if (! $assignment instanceof CredentialAssignment) {
                $credential->forceFill([
                    'status' => CredentialStatus::Reserved,
                    'reserved_at' => $now,
                    'assigned_service_id' => $lockedService->id,
                ])->save();

                $assignment = CredentialAssignment::create([
                    'upstream_credential_id' => $credential->id,
                    'service_id' => $lockedService->id,
                    'assigned_at' => $now,
                    'metadata' => ['allocation' => 'network_activation'],
                ]);
            } elseif ($credential->assigned_service_id !== $lockedService->id) {
                return DriverResult::failure('upstream_credential_assignment_mismatch');
            }

            $credential->forceFill([
                'status' => CredentialStatus::Active,
                'reserved_at' => null,
                'assigned_service_id' => $lockedService->id,
                'assigned_at' => $credential->assigned_at ?? $now,
            ])->save();

            return DriverResult::success('Upstream credential activated.', [
                'action' => 'activate',
                'credential_id' => $credential->id,
                'assignment_id' => $assignment->id,
                'identifier' => $credential->identifier,
            ]);
        });
    }

    private function release(Service $service, string $reason): DriverResult
    {
        return DB::transaction(function () use ($service, $reason): DriverResult {
            $assignments = CredentialAssignment::query()
                ->where('service_id', $service->id)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                return DriverResult::success('No upstream credential was assigned.', [
                    'action' => $reason,
                    'released' => false,
                ]);
            }

            $released = 0;
            $now = now();
            foreach ($assignments as $assignment) {
                $credential = UpstreamCredential::query()->lockForUpdate()->find($assignment->upstream_credential_id);
                if (! $credential instanceof UpstreamCredential) {
                    continue;
                }

                $nextStatus = $credential->expires_at !== null && $credential->expires_at->isPast()
                    ? CredentialStatus::Expired
                    : CredentialStatus::Available;
                $credential->forceFill([
                    'status' => $nextStatus,
                    'reserved_at' => null,
                    'assigned_service_id' => null,
                    'assigned_at' => null,
                ])->save();
                $assignment->forceFill([
                    'released_at' => $now,
                    'release_reason' => $reason,
                ])->save();
                $released++;
            }

            return DriverResult::success('Upstream credential released.', [
                'action' => $reason,
                'released' => $released > 0,
                'released_count' => $released,
            ]);
        });
    }

    private function disconnect(Service $service, NetworkCommand $command): DriverResult
    {
        if (($command->payload['reason'] ?? null) === 'service_terminated') {
            return $this->release($service, 'service_terminated');
        }

        return DriverResult::success('Upstream credential remains assigned; no session disconnect is required.', [
            'action' => 'disconnect',
            'released' => false,
        ]);
    }
}
