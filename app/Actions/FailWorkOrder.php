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

final readonly class FailWorkOrder implements Action
{
    public function handle(WorkOrder $workOrder, User $actor, string $reason, ?string $notes = null, ?CarbonImmutable $rescheduleAt = null): WorkOrder
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('A failure reason is required.');
        }
        if ((int) $workOrder->tenant_id !== (int) $actor->tenant_id || (int) $workOrder->assigned_to !== (int) $actor->id) {
            throw new DomainException('Only the assigned technician can fail this work order.');
        }

        return DB::transaction(function () use ($workOrder, $actor, $reason, $notes, $rescheduleAt): WorkOrder {
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if ((int) $locked->tenant_id !== (int) $actor->tenant_id || (int) $locked->assigned_to !== (int) $actor->id) {
                throw new DomainException('Only the assigned technician can fail this work order.');
            }
            if (! in_array($locked->status, [WorkOrderStatus::Assigned, WorkOrderStatus::EnRoute, WorkOrderStatus::InProgress], true)) {
                throw new DomainException('Only active assigned work orders can be failed.');
            }

            $fromStatus = $locked->status;
            $locked->forceFill([
                'status' => WorkOrderStatus::Failed,
                'failure_reason' => $reason,
                'completion_notes' => $notes,
                'scheduled_at' => $rescheduleAt ?? $locked->scheduled_at,
            ])->save();
            WorkOrderEvent::create([
                'work_order_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => 'failed',
                'from_status' => $fromStatus->value,
                'to_status' => WorkOrderStatus::Failed->value,
                'metadata' => array_filter([
                    'reason' => $reason,
                    'notes' => $notes,
                    'reschedule_at' => $rescheduleAt?->toIso8601String(),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ]);

            return $locked->refresh();
        });
    }
}
