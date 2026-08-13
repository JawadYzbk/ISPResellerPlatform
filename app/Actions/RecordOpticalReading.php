<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\OpticalDevice;
use App\Models\OpticalReading;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkOrder;
use DomainException;

final readonly class RecordOpticalReading implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, OpticalDevice $device, array $data, ?Service $service = null, ?WorkOrder $workOrder = null): OpticalReading
    {
        if ($device->tenant_id !== $actor->tenant_id) {
            throw new DomainException('The optical device does not belong to this workspace.');
        }
        if ($service instanceof Service && $service->tenant_id !== $actor->tenant_id) {
            throw new DomainException('The service does not belong to this workspace.');
        }
        if ($workOrder instanceof WorkOrder && $workOrder->tenant_id !== $actor->tenant_id) {
            throw new DomainException('The work order does not belong to this workspace.');
        }
        if ($service instanceof Service && $workOrder instanceof WorkOrder && $workOrder->service_id !== $service->id) {
            throw new DomainException('The work order and service must refer to the same installation.');
        }

        return OpticalReading::create([
            ...$data,
            'tenant_id' => $actor->tenant_id,
            'optical_device_id' => $device->id,
            'service_id' => $service?->id,
            'work_order_id' => $workOrder?->id,
            'created_by_id' => $actor->id,
            'source' => $data['source'] ?? 'manual',
        ]);
    }
}
