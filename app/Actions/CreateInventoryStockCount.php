<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryItem;
use App\Models\InventoryStockCount;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockQuantity;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateInventoryStockCount implements Action
{
    /** @param list<array{inventory_item_id: int, counted_quantity: string}> $lines */
    public function handle(User $counter, Warehouse $warehouse, array $lines, ?string $note = null): InventoryStockCount
    {
        if ((int) $warehouse->tenant_id !== (int) $counter->tenant_id || (int) $warehouse->assigned_user_id !== $counter->id) {
            throw new DomainException('You can only count stock assigned to you.');
        }
        if ($lines === []) {
            throw new DomainException('Count at least one stock item.');
        }

        return DB::transaction(function () use ($counter, $warehouse, $lines, $note): InventoryStockCount {
            $count = InventoryStockCount::create([
                'warehouse_id' => $warehouse->id,
                'counted_by_id' => $counter->id,
                'status' => 'pending',
                'note' => filled($note) ? trim((string) $note) : null,
                'counted_at' => now(),
            ]);
            foreach ($lines as $line) {
                $item = InventoryItem::query()->findOrFail($line['inventory_item_id']);
                $balance = StockBalance::query()->where('warehouse_id', $warehouse->id)->where('inventory_item_id', $item->id)->lockForUpdate()->first();
                if ($item->is_serialized || ! $item->is_active || ! $balance instanceof StockBalance) {
                    throw new DomainException('Every counted item must be active bulk stock at this location.');
                }
                $counted = StockQuantity::format($line['counted_quantity']);
                if (StockQuantity::greaterThan('0.000', $counted)) {
                    throw new DomainException('Counted quantities cannot be negative.');
                }
                $expected = (string) $balance->quantity;
                $count->lines()->create([
                    'inventory_item_id' => $item->id,
                    'expected_quantity' => $expected,
                    'counted_quantity' => $counted,
                    'variance_quantity' => StockQuantity::subtract($counted, $expected),
                ]);
            }

            return $count->load('lines.item');
        });
    }
}
