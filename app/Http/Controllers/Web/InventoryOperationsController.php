<?php

namespace App\Http\Controllers\Web;

use App\Actions\AssignInventoryUnit;
use App\Actions\CreateInventoryItem;
use App\Actions\CreateWarehouse;
use App\Actions\ListBulkStock;
use App\Actions\ListInventoryMovements;
use App\Actions\ListInventoryUnits;
use App\Actions\ReceiveBulkStock;
use App\Actions\ReceiveInventoryUnit;
use App\Actions\TransferInventoryUnit;
use App\Actions\UpdateInventoryItem;
use App\Actions\UpdateWarehouse;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryOperationsController extends Controller
{
    public function index(Request $request, ListInventoryUnits $listInventoryUnits, ListBulkStock $listBulkStock, ListInventoryMovements $listInventoryMovements): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.view'), 403);
        $units = $listInventoryUnits->handle(
            $request->string('status')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );
        $rows = $units->getCollection()->map(function (mixed $unit): array {
            if (! $unit instanceof InventoryUnit) {
                throw new \LogicException('Inventory paginator contained an invalid record.');
            }

            return [
                'id' => $unit->id,
                'serial_number' => $unit->serial_number,
                'status' => $unit->status,
                'assigned_at' => $this->isoDate($unit->assigned_at),
                'item' => $unit->item === null ? null : [
                    'sku' => $unit->item->sku,
                    'name' => $unit->item->name,
                    'category' => $unit->item->category,
                ],
                'warehouse' => $unit->warehouse === null ? null : [
                    'code' => $unit->warehouse->code,
                    'name' => $unit->warehouse->name,
                ],
                'service' => $unit->service === null ? null : [
                    'public_id' => $unit->service->public_id,
                    'username' => $unit->service->username,
                    'customer_public_id' => $unit->service->customer?->public_id,
                    'customer' => $unit->service->customer === null ? null : $unit->service->customer->full_name,
                ],
            ];
        })->values();
        $units = new LengthAwarePaginator(
            $rows,
            $units->total(),
            $units->perPage(),
            $units->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $canAssign = $user->can('inventory.assign');
        $canReceive = $user->can('inventory.receive');
        $canTransfer = $user->can('inventory.transfer');
        $assignableServices = $canAssign
            ? Service::query()
                ->with('customer')
                ->where('status', '!=', 'terminated')
                ->orderBy('username')
                ->get(['id', 'public_id', 'customer_id', 'username'])
                ->map(fn (Service $service): array => [
                    'public_id' => $service->public_id,
                    'username' => $service->username,
                    'customer' => $service->customer?->full_name,
                ])
                ->values()
                ->all()
            : [];

        $bulkBalances = $listBulkStock->handle()->map(fn ($balance): array => [
            'inventory_item_id' => $balance->inventory_item_id,
            'warehouse_id' => $balance->warehouse_id,
            'sku' => $balance->item?->sku,
            'name' => $balance->item?->name,
            'warehouse' => $balance->warehouse?->code,
            'quantity' => (string) $balance->quantity,
        ])->values();

        return Inertia::render('Operations/Inventory', [
            'units' => $units,
            'filters' => $request->only(['status', 'search']),
            'canAssign' => $canAssign,
            'canReceive' => $canReceive,
            'canTransfer' => $canTransfer,
            'assignableServices' => $assignableServices,
            'bulkBalances' => $bulkBalances,
            'movements' => $listInventoryMovements->handle($request->string('movement_type')->toString() ?: null),
            'bulkItems' => $canReceive
                ? InventoryItem::query()->where('is_serialized', false)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name'])->values()
                : [],
            'serializedItems' => $canReceive
                ? InventoryItem::query()->where('is_serialized', true)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name'])->values()
                : [],
            'catalogItems' => $canReceive
                ? InventoryItem::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'sku', 'name', 'category', 'is_serialized', 'reorder_level', 'is_active'])->values()
                : [],
            'bulkWarehouses' => $canReceive
                ? Warehouse::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->values()
                : [],
            'catalogWarehouses' => $canReceive
                ? Warehouse::query()->orderByDesc('is_active')->orderBy('code')->get(['id', 'code', 'name', 'type', 'is_active'])->values()
                : [],
            'transferWarehouses' => $canTransfer
                ? Warehouse::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->values()
                : [],
        ]);
    }

    public function storeItem(Request $request, CreateInventoryItem $create): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.receive'), 403);
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:32'],
            'is_serialized' => ['required', 'boolean'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
        ]);
        $sku = strtoupper(trim((string) $validated['sku']));
        if (InventoryItem::query()->where('sku', $sku)->exists()) {
            throw ValidationException::withMessages(['sku' => 'An inventory item with this SKU already exists.']);
        }
        $item = $create->handle($validated);

        return redirect()->route('operations.inventory')->with('success', "Inventory item {$item->sku} created.");
    }

    public function storeWarehouse(Request $request, CreateWarehouse $create): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.receive'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32'],
            'type' => ['required', 'string', 'max:16', 'in:warehouse,van'],
        ]);
        $code = strtoupper(trim((string) $validated['code']));
        if (Warehouse::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'A warehouse with this code already exists.']);
        }
        $warehouse = $create->handle($validated);

        return redirect()->route('operations.inventory')->with('success', "Warehouse {$warehouse->code} created.");
    }

    public function updateItem(Request $request, InventoryItem $item, UpdateInventoryItem $update): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.receive'), 403);
        $request->merge([
            'sku' => strtoupper(trim($request->string('sku')->toString())),
            'category' => strtolower(trim($request->string('category')->toString())),
        ]);
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:64', Rule::unique('inventory_items', 'sku')->ignore($item->id)->where('tenant_id', $user->tenant_id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:32'],
            'is_serialized' => ['required', 'boolean'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);
        try {
            $updated = $update->handle($item, $validated);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['is_serialized' => $exception->getMessage()]);
        }

        return redirect()->route('operations.inventory')->with('success', "Inventory item {$updated->sku} updated.");
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse, UpdateWarehouse $update): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.receive'), 403);
        $request->merge(['code' => strtoupper(trim($request->string('code')->toString()))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('warehouses', 'code')->ignore($warehouse->id)->where('tenant_id', $user->tenant_id)],
            'type' => ['required', 'string', 'max:16', 'in:warehouse,van'],
            'is_active' => ['required', 'boolean'],
        ]);
        $updated = $update->handle($warehouse, $validated);

        return redirect()->route('operations.inventory')->with('success', "Warehouse {$updated->code} updated.");
    }

    public function receiveUnit(Request $request, ReceiveInventoryUnit $receive): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.receive'), 403);
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'serial_number' => ['required', 'string', 'max:128'],
        ]);
        $item = InventoryItem::query()->findOrFail($validated['inventory_item_id']);
        $warehouse = Warehouse::query()->findOrFail($validated['warehouse_id']);
        try {
            $unit = $receive->handle($item, $warehouse, $user, (string) $validated['serial_number']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['serial_number' => $exception->getMessage()]);
        }

        return redirect()->route('operations.inventory')->with('success', "Serialized unit {$unit->serial_number} received.");
    }

    public function receiveBulk(Request $request, ReceiveBulkStock $receive): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.receive'), 403);
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $item = InventoryItem::query()->findOrFail($validated['inventory_item_id']);
        $warehouse = Warehouse::query()->findOrFail($validated['warehouse_id']);
        try {
            $receive->handle($item, $warehouse, $user, (string) $validated['quantity'], $validated['note'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        return redirect()->route('operations.inventory')->with('success', 'Bulk stock received.');
    }

    public function assign(Request $request, InventoryUnit $unit, AssignInventoryUnit $assign): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.assign'), 403);
        $validated = $request->validate(['service_public_id' => ['required', 'string']]);
        $service = Service::query()->where('public_id', $validated['service_public_id'])->firstOrFail();
        $assign->handle($unit, $service, $user);

        return redirect()->route('operations.inventory')->with('success', "Inventory unit {$unit->serial_number} assigned.");
    }

    public function transfer(Request $request, InventoryUnit $unit, TransferInventoryUnit $transfer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.transfer'), 403);
        $validated = $request->validate(['warehouse_id' => ['required', 'integer']]);
        $warehouse = Warehouse::query()->findOrFail($validated['warehouse_id']);
        try {
            $transfer->handle($unit, $warehouse, $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['warehouse_id' => $exception->getMessage()]);
        }

        return redirect()->route('operations.inventory')->with('success', "Inventory unit {$unit->serial_number} transferred.");
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
