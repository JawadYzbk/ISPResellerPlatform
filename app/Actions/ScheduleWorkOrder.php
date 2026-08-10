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

final readonly class ScheduleWorkOrder implements Action
{
    public function handle(WorkOrder $workOrder, User $actor, CarbonImmutable $scheduledAt): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $actor, $scheduledAt): WorkOrder {
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if (in_array($locked->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
                throw new DomainException('Completed or cancelled work orders cannot be rescheduled.');
            }

            $previous = $locked->scheduled_at?->toIso8601String();
            $locked->forceFill(['scheduled_at' => $scheduledAt])->save();
            WorkOrderEvent::create([
                'work_order_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => 'rescheduled',
                'metadata' => ['previous_scheduled_at' => $previous, 'scheduled_at' => $scheduledAt->toIso8601String()],
            ]);

            return $locked->refresh();
        });
    }
}
