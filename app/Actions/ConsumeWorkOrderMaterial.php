<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\WorkOrderStatus;
use App\Models\InventoryItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderMaterial;
use App\Support\StockQuantity;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ConsumeWorkOrderMaterial implements Action
{
    public function handle(WorkOrder $workOrder, InventoryItem $item, Warehouse $warehouse, User $actor, string $quantity, ?string $note = null): WorkOrderMaterial
    {
        $normalized = StockQuantity::normalize($quantity);
        if ((int) $workOrder->tenant_id !== (int) $item->tenant_id
            || (int) $item->tenant_id !== (int) $warehouse->tenant_id
            || (int) $actor->tenant_id !== (int) $item->tenant_id) {
            throw new DomainException('The work order, item, warehouse, and actor must belong to the same tenant.');
        }
        if ($item->is_serialized) {
            throw new DomainException('Serialized inventory must be assigned as an individual unit.');
        }
        if (! $item->is_active || ! $warehouse->is_active) {
            throw new DomainException('Only active inventory items and warehouses can provide materials.');
        }
        if (! $actor->can('inventory.receive') && $warehouse->assigned_user_id !== $actor->id) {
            throw new DomainException('Technicians may consume materials only from their assigned van warehouse.');
        }
        if (! $actor->can('inventory.receive') && $workOrder->assigned_to !== $actor->id) {
            throw new DomainException('Only the assigned technician can consume materials for this work order.');
        }

        return DB::transaction(function () use ($workOrder, $item, $warehouse, $actor, $normalized, $note): WorkOrderMaterial {
            $lockedOrder = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if (in_array($lockedOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
                throw new DomainException('Completed or cancelled work orders cannot consume new materials.');
            }

            $balance = StockBalance::query()
                ->where('inventory_item_id', $item->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->first();
            if ($balance === null || StockQuantity::greaterThan($normalized, (string) $balance->quantity)) {
                throw new DomainException('Insufficient bulk stock for this material.');
            }

            $balance->forceFill(['quantity' => StockQuantity::subtract((string) $balance->quantity, $normalized)])->save();
            $material = WorkOrderMaterial::create([
                'work_order_id' => $lockedOrder->id,
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'consumed_by_id' => $actor->id,
                'quantity' => $normalized,
                'note' => filled($note) ? trim($note) : null,
                'consumed_at' => now(),
            ]);
            StockMovement::create([
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'work_order_id' => $lockedOrder->id,
                'actor_id' => $actor->id,
                'movement_type' => 'consume',
                'quantity' => StockQuantity::subtract('0.000', $normalized),
                'note' => filled($note) ? trim($note) : null,
                'occurred_at' => now(),
            ]);
            WorkOrderEvent::create([
                'work_order_id' => $lockedOrder->id,
                'actor_id' => $actor->id,
                'event_type' => 'material_consumed',
                'metadata' => ['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => $normalized],
            ]);

            return $material->load(['item', 'warehouse']);
        });
    }
}
