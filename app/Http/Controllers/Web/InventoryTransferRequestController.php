<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateInventoryTransferRequest;
use App\Actions\ReviewInventoryTransferRequest;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTransferRequest;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InventoryTransferRequestController extends Controller
{
    public function store(Request $request, CreateInventoryTransferRequest $create): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && in_array($user->role, ['collector', 'technician'], true), 403);
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'source_warehouse_id' => ['required', 'integer'],
            'destination_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id'],
            'type' => ['required', 'string', 'in:replenishment,return'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $created = $create->handle(
                $user,
                InventoryItem::query()->findOrFail($validated['inventory_item_id']),
                Warehouse::query()->findOrFail($validated['source_warehouse_id']),
                Warehouse::query()->findOrFail($validated['destination_warehouse_id']),
                (string) $validated['type'],
                (string) $validated['quantity'],
                $validated['note'] ?? null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        return redirect()->route('field.index')->with('success', ucfirst($created->type).' request created.');
    }

    public function review(Request $request, InventoryTransferRequest $inventoryTransferRequest, ReviewInventoryTransferRequest $review): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.transfer'), 403);
        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $reviewed = $review->handle($user, $inventoryTransferRequest, (string) $validated['decision'], $validated['review_note'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return redirect()->route('operations.inventory')->with('success', 'Stock request '.$reviewed->status.'.');
    }
}
