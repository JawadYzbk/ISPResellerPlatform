<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\OpticalDevice;
use App\Models\Tenant;

final readonly class SaveOpticalDevice implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Tenant $tenant, array $data, ?OpticalDevice $device = null): OpticalDevice
    {
        $device ??= new OpticalDevice;
        $device->fill([...$data, 'tenant_id' => $tenant->id]);
        $device->save();

        return $device->refresh();
    }
}
