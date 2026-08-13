<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AcceptWorkOrderInstallation implements Action
{
    public function handle(WorkOrder $workOrder, User $actor, ?string $note = null): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $actor, $note): WorkOrder {
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if ((int) $locked->tenant_id !== (int) $actor->tenant_id) {
                throw new DomainException('The work order and operator must belong to the same tenant.');
            }
            $canManageAllInstallations = $actor->can('network.provision') || $actor->can('reports.operations');

            if (! $canManageAllInstallations && (int) $locked->assigned_to !== (int) $actor->id) {
                throw new DomainException('Only the assigned technician can accept this installation.');
            }
            if (! in_array($locked->type, ['installation', 'fiber'], true)) {
                throw new DomainException('Activation acceptance is available only for installation work orders.');
            }
            if (! in_array($locked->status, [WorkOrderStatus::Assigned, WorkOrderStatus::EnRoute, WorkOrderStatus::InProgress], true)) {
                throw new DomainException('Activation can be accepted only while the installation is assigned or in progress.');
            }
            if ($locked->network_building_id === null || $locked->distribution_box_id === null || $locked->network_port === null) {
                throw new DomainException('Assign a building, distribution box, and port before accepting activation.');
            }
            if ($locked->type === 'fiber' && blank($locked->onu_serial)) {
                throw new DomainException('Record the ONU serial before accepting fiber activation.');
            }
            if ($locked->activation_accepted_at !== null) {
                return $locked;
            }

            $locked->forceFill([
                'activation_accepted_at' => now(),
                'activation_accepted_by_id' => $actor->id,
                'activation_acceptance_note' => filled($note) ? trim((string) $note) : null,
            ])->save();
            WorkOrderEvent::create([
                'work_order_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => 'activation_accepted',
                'metadata' => [
                    'building_id' => $locked->network_building_id,
                    'box_id' => $locked->distribution_box_id,
                    'port' => $locked->network_port,
                    'onu_serial' => $locked->onu_serial,
                    'note' => $locked->activation_acceptance_note,
                ],
            ]);

            return $locked->refresh();
        });
    }
}
