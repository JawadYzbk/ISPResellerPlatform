<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\WorkOrderStatus;
use App\Models\DistributionBox;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class SaveWorkOrderInstallation implements Action
{
    public function __construct(private AssignServiceTopology $assignServiceTopology) {}

    /** @param array<string, scalar|null> $survey */
    public function handle(WorkOrder $workOrder, DistributionBox $box, int $port, array $survey, ?string $onuSerial, User $actor): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $box, $port, $survey, $onuSerial, $actor): WorkOrder {
            $locked = WorkOrder::query()->with('service')->lockForUpdate()->findOrFail($workOrder->id);
            $this->assertCanEdit($locked, $actor);

            if (! in_array($locked->type, ['installation', 'fiber'], true)) {
                throw new DomainException('Topology assignment is available only for installation work orders.');
            }
            if ($locked->service === null) {
                throw new DomainException('The installation work order must have a service before topology can be assigned.');
            }

            $service = $this->assignServiceTopology->handle($locked->service, $box, $port, $actor);
            $locked->forceFill([
                'network_building_id' => $service->network_building_id,
                'distribution_box_id' => $service->distribution_box_id,
                'network_port' => $service->network_port,
                'onu_serial' => filled($onuSerial) ? trim((string) $onuSerial) : null,
                'installation_survey' => $survey,
            ])->save();
            WorkOrderEvent::create([
                'work_order_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => 'installation_saved',
                'metadata' => [
                    'building_id' => $service->network_building_id,
                    'box_id' => $service->distribution_box_id,
                    'port' => $service->network_port,
                    'onu_serial' => $locked->onu_serial,
                    'survey' => $survey,
                ],
            ]);

            return $locked->refresh();
        });
    }

    private function assertCanEdit(WorkOrder $workOrder, User $actor): void
    {
        if ($workOrder->tenant_id !== $actor->tenant_id) {
            throw new DomainException('The work order and technician must belong to the same tenant.');
        }
        if (in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
            throw new DomainException('Completed or cancelled work orders cannot change installation details.');
        }
        $canManageAllInstallations = $actor->can('network.provision') || $actor->can('reports.operations');

        if (! $canManageAllInstallations && $workOrder->assigned_to !== $actor->id) {
            throw new DomainException('Only the assigned technician can update this installation.');
        }
    }
}
