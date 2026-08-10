<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateWorkOrderStatus implements Action
{
    public function handle(WorkOrder $workOrder, User $actor, WorkOrderStatus $status, ?CarbonImmutable $at = null): WorkOrder
    {
        if ((int) $workOrder->tenant_id !== (int) $actor->tenant_id || (int) $workOrder->assigned_to !== (int) $actor->id) {
            throw new DomainException('Only the assigned technician can update this work order.');
        }

        return DB::transaction(function () use ($workOrder, $actor, $status, $at): WorkOrder {
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if ((int) $locked->tenant_id !== (int) $actor->tenant_id || (int) $locked->assigned_to !== (int) $actor->id) {
                throw new DomainException('Only the assigned technician can update this work order.');
            }

            $fromStatus = $locked->status;
            $allowed = match ($fromStatus) {
                WorkOrderStatus::Assigned => [WorkOrderStatus::EnRoute, WorkOrderStatus::InProgress],
                WorkOrderStatus::EnRoute => [WorkOrderStatus::EnRoute, WorkOrderStatus::InProgress],
                WorkOrderStatus::InProgress => [WorkOrderStatus::InProgress],
                default => [],
            };

            if (! in_array($status, $allowed, true)) {
                throw new DomainException('The work order cannot move from '.$fromStatus->value.' to '.$status->value.'.');
            }

            if ($fromStatus === $status) {
                return $locked->refresh();
            }

            $at ??= CarbonImmutable::now();
            $attributes = ['status' => $status];
            if ($status === WorkOrderStatus::InProgress && $locked->started_at === null) {
                $attributes['started_at'] = $at;
            }
            $locked->forceFill($attributes)->save();
            WorkOrderEvent::create([
                'work_order_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => 'status_changed',
                'from_status' => $fromStatus->value,
                'to_status' => $status->value,
                'metadata' => ['at' => $at->toIso8601String()],
            ]);

            return $locked->refresh();
        });
    }
}
