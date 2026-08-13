<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryItem;
use App\Models\InventoryTransferRequest;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReviewInventoryTransferRequest implements Action
{
    public function __construct(private TransferBulkStock $transfer) {}

    public function handle(User $manager, InventoryTransferRequest $request, string $decision, ?string $note = null): InventoryTransferRequest
    {
        if (! $manager->can('inventory.transfer') || (int) $manager->tenant_id !== (int) $request->tenant_id) {
            throw new DomainException('You are not allowed to review this stock request.');
        }
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainException('Choose approve or reject.');
        }

        return DB::transaction(function () use ($manager, $request, $decision, $note): InventoryTransferRequest {
            $locked = InventoryTransferRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== 'pending') {
                throw new DomainException('This stock request has already been reviewed.');
            }
            if ($decision === 'approved') {
                $item = InventoryItem::query()->findOrFail($locked->inventory_item_id);
                $source = Warehouse::query()->findOrFail($locked->source_warehouse_id);
                $destination = Warehouse::query()->findOrFail($locked->destination_warehouse_id);
                $this->transfer->handle($item, $source, $destination, $manager, (string) $locked->quantity, 'Approved request '.$locked->public_id);
            }
            $locked->forceFill([
                'status' => $decision,
                'reviewed_by_id' => $manager->id,
                'reviewed_at' => now(),
                'review_note' => filled($note) ? trim((string) $note) : null,
            ])->save();

            return $locked->refresh();
        });
    }
}
