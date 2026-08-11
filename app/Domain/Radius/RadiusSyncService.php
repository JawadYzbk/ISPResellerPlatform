<?php

namespace App\Domain\Radius;

use App\Enums\ServiceStatus;
use App\Models\RadiusGroupReply;
use App\Models\RadiusNas;
use App\Models\RadiusUser;
use App\Models\RadiusUserGroup;
use App\Models\Router;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

final class RadiusSyncService
{
    public function sync(Service $service): void
    {
        $service->loadMissing(['plan', 'router']);
        $group = in_array($service->status, [ServiceStatus::Suspended, ServiceStatus::Paused], true) ? 'suspended' : 'plan-'.$service->plan_id;
        DB::transaction(function () use ($service, $group): void {
            if ($service->router instanceof Router) {
                $this->syncRouterRecord($service->router);
            }
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

    public function syncRouter(Router $router): RadiusNas
    {
        return DB::transaction(fn (): RadiusNas => $this->syncRouterRecord($router));
    }

    private function syncRouterRecord(Router $router): RadiusNas
    {
        return RadiusNas::updateOrCreate(
            ['tenant_id' => $router->tenant_id, 'nasname' => $router->host],
            [
                'shortname' => $router->name,
                'secret' => $router->radius_secret_encrypted,
                'coa_port' => $router->coa_port,
            ],
        );
    }
}
