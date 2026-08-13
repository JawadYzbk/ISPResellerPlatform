<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryStockCount;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReviewInventoryStockCount implements Action
{
    public function handle(User $manager, InventoryStockCount $count, string $decision, ?string $note = null): InventoryStockCount
    {
        if (! $manager->can('inventory.transfer') || (int) $manager->tenant_id !== (int) $count->tenant_id) {
            throw new DomainException('You are not allowed to review this stock count.');
        }
        if (! in_array($decision, ['posted', 'rejected'], true)) {
            throw new DomainException('Choose post variance or reject.');
        }

        return DB::transaction(function () use ($manager, $count, $decision, $note): InventoryStockCount {
            $locked = InventoryStockCount::query()->lockForUpdate()->findOrFail($count->id);
            if ($locked->status !== 'pending') {
                throw new DomainException('This stock count has already been reviewed.');
            }
            $lines = $locked->lines()->orderBy('inventory_item_id')->get();
            if ($decision === 'posted') {
                foreach ($lines as $line) {
                    $balance = StockBalance::query()->where('warehouse_id', $locked->warehouse_id)->where('inventory_item_id', $line->inventory_item_id)->lockForUpdate()->firstOrFail();
                    if ((string) $balance->quantity !== (string) $line->expected_quantity) {
                        throw new DomainException('Stock changed after this count. Reject it and submit a fresh count.');
                    }
                    $balance->forceFill(['quantity' => $line->counted_quantity])->save();
                    if ((string) $line->variance_quantity !== '0.000') {
                        StockMovement::create([
                            'inventory_item_id' => $line->inventory_item_id,
                            'warehouse_id' => $locked->warehouse_id,
                            'actor_id' => $manager->id,
                            'movement_type' => 'count_adjustment',
                            'quantity' => $line->variance_quantity,
                            'note' => filled($note) ? trim((string) $note) : 'Approved physical stock count',
                            'occurred_at' => now(),
                            'metadata' => ['stock_count_id' => $locked->public_id, 'expected_quantity' => $line->expected_quantity, 'counted_quantity' => $line->counted_quantity],
                        ]);
                    }
                }
            }
            $locked->forceFill(['status' => $decision, 'reviewed_by_id' => $manager->id, 'reviewed_at' => now(), 'review_note' => filled($note) ? trim((string) $note) : null])->save();

            return $locked->refresh()->load('lines.item');
        });
    }
}
