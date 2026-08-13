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

    public function handle(WorkOrder $workOrder, ?User $actor = null, ?string $idempotencyKey = null): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $actor, $idempotencyKey): WorkOrder {
            $locked = WorkOrder::query()->with('service')->lockForUpdate()->findOrFail($workOrder->id);
            if ($idempotencyKey !== null) {
                $usedBy = WorkOrder::query()->where('completion_idempotency_key', $idempotencyKey)->first();
                if ($usedBy !== null && $usedBy->id !== $locked->id) {
                    throw new DomainException('The completion idempotency key was already used for another work order.');
                }
            }
            if ($locked->status === WorkOrderStatus::Completed) {
                return $locked;
            }
            if (! in_array($locked->status, [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress], true)) {
                throw new DomainException('Only assigned or in-progress work orders can be completed.');
            }
            if ($locked->type === 'installation'
                && (bool) ($locked->metadata['requires_installation_acceptance'] ?? false)
                && $locked->activation_accepted_at === null) {
                throw new DomainException('Record topology and accept activation before completing this installation.');
            }
            $fromStatus = $locked->status;
            $locked->forceFill(['status' => WorkOrderStatus::Completed, 'completed_at' => now(), 'completion_idempotency_key' => $idempotencyKey])->save();
            WorkOrderEvent::create(['work_order_id' => $locked->id, 'actor_id' => $actor?->id, 'event_type' => 'completed', 'from_status' => $fromStatus->value, 'to_status' => WorkOrderStatus::Completed->value]);
            if ($locked->type === 'installation' && $locked->service !== null && $locked->service->status !== ServiceStatus::Active) {
                $service = $this->transitionService->handle($locked->service, ServiceStatus::Active, $actor);
                $this->enqueueNetworkCommand->handle($service, 'activate');
            }

            return $locked->refresh();
        });
    }
}
