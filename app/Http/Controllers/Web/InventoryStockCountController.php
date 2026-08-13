<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateInventoryStockCount;
use App\Actions\ReviewInventoryStockCount;
use App\Http\Controllers\Controller;
use App\Models\InventoryStockCount;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InventoryStockCountController extends Controller
{
    public function store(Request $request, CreateInventoryStockCount $create): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && in_array($user->role, ['collector', 'technician'], true), 403);
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.inventory_item_id' => ['required', 'integer', 'distinct'],
            'lines.*.counted_quantity' => ['required', 'string', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $create->handle($user, Warehouse::query()->findOrFail($validated['warehouse_id']), $validated['lines'], $validated['note'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        return redirect()->route('field.index')->with('success', 'Stock count submitted.');
    }

    public function review(Request $request, InventoryStockCount $inventoryStockCount, ReviewInventoryStockCount $review): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.transfer'), 403);
        $validated = $request->validate(['decision' => ['required', 'string', 'in:posted,rejected'], 'review_note' => ['nullable', 'string', 'max:500']]);
        try {
            $reviewed = $review->handle($user, $inventoryStockCount, (string) $validated['decision'], $validated['review_note'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return redirect()->route('operations.inventory')->with('success', 'Stock count '.$reviewed->status.'.');
    }
}
