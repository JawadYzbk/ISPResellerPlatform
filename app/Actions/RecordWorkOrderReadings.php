<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class RecordWorkOrderReadings implements Action
{
    /** @param array<string, string> $readings */
    public function handle(WorkOrder $workOrder, User $actor, array $readings): WorkOrder
    {
        if ((int) $workOrder->tenant_id !== (int) $actor->tenant_id) {
            throw new DomainException('The work order and technician must belong to the same tenant.');
        }

        return DB::transaction(function () use ($workOrder, $actor, $readings): WorkOrder {
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if (in_array($locked->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
                throw new DomainException('Completed or cancelled work orders cannot receive new readings.');
            }
            if ($locked->assigned_to !== $actor->id) {
                throw new DomainException('Only the assigned technician can record readings.');
            }
            $locked->forceFill(['readings' => $readings])->save();
            WorkOrderEvent::create([
                'work_order_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => 'readings_recorded',
                'metadata' => ['readings' => $readings],
            ]);

            return $locked->refresh();
        });
    }
}
