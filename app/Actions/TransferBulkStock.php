<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockQuantity;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class TransferBulkStock implements Action
{
    public function handle(InventoryItem $item, Warehouse $source, Warehouse $destination, User $actor, string $quantity, ?string $note = null): void
    {
        $normalized = StockQuantity::normalize($quantity);
        if ((int) $item->tenant_id !== (int) $source->tenant_id
            || (int) $source->tenant_id !== (int) $destination->tenant_id
            || (int) $actor->tenant_id !== (int) $item->tenant_id) {
            throw new DomainException('The item, stock locations, and actor must belong to the same tenant.');
        }
        if ($item->is_serialized || ! $item->is_active || ! $source->is_active || ! $destination->is_active) {
            throw new DomainException('Only active bulk items and stock locations can be transferred.');
        }
        if ($source->id === $destination->id) {
            throw new DomainException('Choose two different stock locations.');
        }

        DB::transaction(function () use ($item, $source, $destination, $actor, $normalized, $note): void {
            InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            StockBalance::query()->firstOrCreate(
                ['inventory_item_id' => $item->id, 'warehouse_id' => $destination->id],
                ['quantity' => '0.000'],
            );
            $balances = StockBalance::query()
                ->where('inventory_item_id', $item->id)
                ->whereIn('warehouse_id', [$source->id, $destination->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('warehouse_id');
            $sourceBalance = $balances->get($source->id);
            $destinationBalance = $balances->get($destination->id);
            if (! $sourceBalance instanceof StockBalance || ! $destinationBalance instanceof StockBalance
                || StockQuantity::greaterThan($normalized, (string) $sourceBalance->quantity)) {
                throw new DomainException('Insufficient stock at the source location.');
            }

            $sourceBalance->forceFill(['quantity' => StockQuantity::subtract((string) $sourceBalance->quantity, $normalized)])->save();
            $destinationBalance->forceFill(['quantity' => StockQuantity::add((string) $destinationBalance->quantity, $normalized)])->save();
            $transferId = (string) Str::ulid();
            StockMovement::create([
                'inventory_item_id' => $item->id,
                'warehouse_id' => $source->id,
                'actor_id' => $actor->id,
                'movement_type' => 'transfer_out',
                'quantity' => StockQuantity::subtract('0.000', $normalized),
                'note' => filled($note) ? trim((string) $note) : null,
                'occurred_at' => now(),
                'metadata' => ['transfer_id' => $transferId, 'counterpart_warehouse_id' => $destination->id],
            ]);
            StockMovement::create([
                'inventory_item_id' => $item->id,
                'warehouse_id' => $destination->id,
                'actor_id' => $actor->id,
                'movement_type' => 'transfer_in',
                'quantity' => $normalized,
                'note' => filled($note) ? trim((string) $note) : null,
                'occurred_at' => now(),
                'metadata' => ['transfer_id' => $transferId, 'counterpart_warehouse_id' => $source->id],
            ]);
        });
    }
}
