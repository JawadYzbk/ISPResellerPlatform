<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\CredentialStatus;
use App\Models\CredentialAssignment;
use App\Models\Service;
use App\Models\UpstreamCredential;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AssignUpstreamCredential implements Action
{
    public function handle(UpstreamCredential $credential, Service $service, ?User $actor = null): CredentialAssignment
    {
        return DB::transaction(function () use ($credential, $service, $actor): CredentialAssignment {
            $locked = UpstreamCredential::query()->lockForUpdate()->findOrFail($credential->id);
            if ($locked->tenant_id !== $service->tenant_id || ($actor !== null && $actor->tenant_id !== $locked->tenant_id)) {
                throw new DomainException('Credentials, services, and actors must belong to the same tenant.');
            }
            if ($locked->status !== CredentialStatus::Available || $locked->assigned_service_id !== null) {
                throw new DomainException('The upstream credential is not available.');
            }
            if (UpstreamCredential::query()->where('assigned_service_id', $service->id)->exists()) {
                throw new DomainException('The service already has an upstream credential.');
            }
            $locked->forceFill(['status' => CredentialStatus::Assigned, 'assigned_service_id' => $service->id, 'assigned_at' => now()])->save();

            return CredentialAssignment::create(['upstream_credential_id' => $locked->id, 'service_id' => $service->id, 'assigned_by' => $actor?->id, 'assigned_at' => now()]);
        });
    }
}
