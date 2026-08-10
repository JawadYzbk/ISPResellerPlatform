<?php

namespace App\Http\Controllers\Web;

use App\Actions\ListInventoryUnits;
use App\Http\Controllers\Controller;
use App\Models\InventoryUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryOperationsController extends Controller
{
    public function index(Request $request, ListInventoryUnits $listInventoryUnits): Response
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

        return Inertia::render('Operations/Inventory', [
            'units' => $units,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
