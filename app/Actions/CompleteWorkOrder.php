<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\ServiceStatus;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CompleteWorkOrder implements Action
{
    public function __construct(private TransitionService $transitionService, private EnqueueNetworkCommand $enqueueNetworkCommand) {}

    public function handle(WorkOrder $workOrder, ?User $actor = null): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $actor): WorkOrder {
            $locked = WorkOrder::query()->with('service')->lockForUpdate()->findOrFail($workOrder->id);
            if ($locked->status === WorkOrderStatus::Completed) {
                return $locked;
            }
            if (! in_array($locked->status, [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress], true)) {
                throw new DomainException('Only assigned or in-progress work orders can be completed.');
            }
            $fromStatus = $locked->status;
            $locked->forceFill(['status' => WorkOrderStatus::Completed, 'completed_at' => now()])->save();
            WorkOrderEvent::create(['work_order_id' => $locked->id, 'actor_id' => $actor?->id, 'event_type' => 'completed', 'from_status' => $fromStatus->value, 'to_status' => WorkOrderStatus::Completed->value]);
            if ($locked->type === 'installation' && $locked->service !== null && $locked->service->status !== ServiceStatus::Active) {
                $service = $this->transitionService->handle($locked->service, ServiceStatus::Active, $actor);
                $this->enqueueNetworkCommand->handle($service, 'activate');
            }

            return $locked->refresh();
        });
    }
}
