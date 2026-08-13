<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryItem;
use App\Models\InventoryTransferRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockQuantity;
use DomainException;

final readonly class CreateInventoryTransferRequest implements Action
{
    public function handle(User $requester, InventoryItem $item, Warehouse $source, Warehouse $destination, string $type, string $quantity, ?string $note = null): InventoryTransferRequest
    {
        $tenantId = (int) $requester->tenant_id;
        if (! in_array($type, InventoryTransferRequest::TYPES, true)) {
            throw new DomainException('Choose replenishment or return.');
        }
        if ((int) $item->tenant_id !== $tenantId || (int) $source->tenant_id !== $tenantId || (int) $destination->tenant_id !== $tenantId) {
            throw new DomainException('The stock request must stay inside this workspace.');
        }
        if ($item->is_serialized || ! $item->is_active || ! $source->is_active || ! $destination->is_active) {
            throw new DomainException('Only active bulk stock can be requested.');
        }
        $custodyLocation = $type === 'replenishment' ? $destination : $source;
        $centralLocation = $type === 'replenishment' ? $source : $destination;
        if ((int) $custodyLocation->assigned_user_id !== $requester->id || $centralLocation->type !== 'warehouse') {
            throw new DomainException('Choose your assigned stock location and an active central warehouse.');
        }
        if (InventoryTransferRequest::query()
            ->where('requested_by_id', $requester->id)
            ->where('inventory_item_id', $item->id)
            ->where('source_warehouse_id', $source->id)
            ->where('destination_warehouse_id', $destination->id)
            ->where('status', 'pending')
            ->exists()) {
            throw new DomainException('A matching stock request is already pending.');
        }

        return InventoryTransferRequest::create([
            'requested_by_id' => $requester->id,
            'inventory_item_id' => $item->id,
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'type' => $type,
            'status' => 'pending',
            'quantity' => StockQuantity::normalize($quantity),
            'note' => filled($note) ? trim((string) $note) : null,
        ]);
    }
}
