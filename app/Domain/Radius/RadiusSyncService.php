<?php

namespace App\Domain\Radius;

use App\Enums\ServiceStatus;
use App\Models\RadiusGroupReply;
use App\Models\RadiusUser;
use App\Models\RadiusUserGroup;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

final class RadiusSyncService
{
    public function sync(Service $service): void
    {
        $service->loadMissing('plan');
        $group = $service->status === ServiceStatus::Suspended ? 'suspended' : 'plan-'.$service->plan_id;
        DB::transaction(function () use ($service, $group): void {
            RadiusUser::updateOrCreate(
                ['service_id' => $service->id, 'username' => $service->username, 'attribute' => 'Cleartext-Password'],
                ['op' => ':=', 'value' => (string) $service->password_encrypted],
            );
            RadiusUserGroup::updateOrCreate(
                ['service_id' => $service->id, 'username' => $service->username],
                ['groupname' => $group],
            );
            if ($group !== 'suspended') {
                RadiusGroupReply::updateOrCreate(
                    ['groupname' => $group, 'attribute' => 'Mikrotik-Rate-Limit'],
                    ['op' => ':=', 'value' => $service->plan->upload_kbps.'k/'.$service->plan->download_kbps.'k'],
                );
            }
        });
    }
}
