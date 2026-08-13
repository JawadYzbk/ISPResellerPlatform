<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetCollectorCustodyPosition;
use App\Actions\GetCollectorShift;
use App\Actions\GetCollectorSummary;
use App\Actions\GetCollectorSyncSnapshot;
use App\Actions\GetCurrencyCatalog;
use App\Actions\PushCollectorSync;
use App\Http\Controllers\Controller;
use App\Models\CollectorCustodyEntry;
use App\Models\CollectorFieldDay;
use App\Models\CollectorRoute;
use App\Models\CollectorTask;
use App\Models\InventoryItem;
use App\Models\InventoryStockCount;
use App\Models\InventoryTransferRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CollectorCustodyPresenter;
use App\Support\CollectorRoutePresenter;
use App\Support\CollectorTaskPresenter;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class FieldController extends Controller
{
    public function index(
        Request $request,
        GetCollectorShift $shift,
        GetCollectorSummary $summary,
        GetCollectorSyncSnapshot $snapshot,
        GetCurrencyCatalog $currencyCatalog,
        CollectorRoutePresenter $routePresenter,
        CollectorTaskPresenter $taskPresenter,
        GetCollectorCustodyPosition $custodyPosition,
        CollectorCustodyPresenter $custodyPresenter,
    ): Response {
        $user = $this->collector($request);
        $tenant = $this->tenant();
        $storageEncryptionKey = $request->session()->get('field_storage_encryption_key');
        if (! is_string($storageEncryptionKey) || $storageEncryptionKey === '') {
            $storageEncryptionKey = base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES));
            $request->session()->put('field_storage_encryption_key', $storageEncryptionKey);
        }

        return Inertia::render('Field/Index', [
            'snapshot' => $snapshot->handle($tenant, $user, null, null),
            'shift' => $shift->handle($user),
            'summary' => $summary->handle($user, now()->toDateString()),
            'fieldDay' => $this->fieldDay($user),
            'route' => $this->route($user, $tenant, $routePresenter),
            'tasks' => CollectorTask::query()
                ->where('collector_id', $user->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->with(['collector:id,name,email', 'createdBy:id,name', 'customer:id,public_id,code,first_name,last_name,phone,address', 'messages.author:id,name,role', 'messages.attachments', 'reads'])
                ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
                ->orderBy('due_at')
                ->limit(50)
                ->get()
                ->map(fn (CollectorTask $task): array => $taskPresenter->make($task, $user))
                ->values(),
            'custody' => [
                'position' => $custodyPosition->handle($user),
                'entries' => CollectorCustodyEntry::query()
                    ->where('collector_id', $user->id)
                    ->with(['collector:id,name,email', 'requestedBy:id,name', 'reviewedBy:id,name', 'cashShift:id,public_id'])
                    ->latest('occurred_at')
                    ->limit(20)
                    ->get()
                    ->map(fn (CollectorCustodyEntry $entry): array => $custodyPresenter->entry($entry))
                    ->values(),
            ],
            'stock' => $this->stock($user),
            'currencies' => $currencyCatalog->handle(),
            'defaultCurrency' => $tenant->collection_currency,
            'storageKey' => 'field:'.$tenant->public_id.':'.$user->id,
            'storageEncryptionKey' => $storageEncryptionKey,
        ]);
    }

    public function sync(Request $request, GetCollectorSyncSnapshot $snapshot): JsonResponse
    {
        $user = $this->collector($request);
        $validated = $request->validate([
            'since' => ['nullable', 'string', 'max:2048'],
            'zone' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            return response()->json($snapshot->handle(
                $this->tenant(),
                $user,
                $validated['zone'] ?? null,
                $validated['since'] ?? null,
            ));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function push(Request $request, PushCollectorSync $push): JsonResponse
    {
        $user = $this->collector($request);
        $items = $request->input('items');
        abort_unless(is_array($items) && count($items) > 0 && count($items) <= 100, 422, 'Provide between one and one hundred queued payments.');

        return response()->json($push->handle(array_values($items), $user));
    }

    private function collector(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->can('customers.view') && $user->can('payments.collect'), 403);

        return $user;
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(app(Tenancy::class)->requireId());
    }

    /** @return array<string, mixed> */
    private function stock(User $user): array
    {
        $locations = Warehouse::query()
            ->where('assigned_user_id', $user->id)
            ->where('is_active', true)
            ->with(['stockBalances.item'])
            ->orderBy('code')
            ->get();

        return [
            'locations' => $locations->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'balances' => $warehouse->stockBalances
                    ->filter(fn ($balance): bool => $balance->item?->is_active === true && ! $balance->item->is_serialized)
                    ->map(fn ($balance): array => [
                        'item_id' => $balance->inventory_item_id,
                        'sku' => $balance->item?->sku,
                        'name' => $balance->item?->name,
                        'quantity' => (string) $balance->quantity,
                    ])->values()->all(),
            ])->values()->all(),
            'central_locations' => Warehouse::query()->where('type', 'warehouse')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->values(),
            'items' => InventoryItem::query()->where('is_serialized', false)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name'])->values(),
            'requests' => InventoryTransferRequest::query()
                ->where('requested_by_id', $user->id)
                ->with(['item:id,sku,name', 'sourceWarehouse:id,code,name', 'destinationWarehouse:id,code,name'])
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (InventoryTransferRequest $stockRequest): array => [
                    'id' => $stockRequest->public_id,
                    'type' => $stockRequest->type,
                    'status' => $stockRequest->status,
                    'quantity' => (string) $stockRequest->quantity,
                    'note' => $stockRequest->note,
                    'review_note' => $stockRequest->review_note,
                    'created_at' => $stockRequest->created_at?->toIso8601String(),
                    'item' => $stockRequest->item?->only(['sku', 'name']),
                    'source' => $stockRequest->sourceWarehouse?->only(['code', 'name']),
                    'destination' => $stockRequest->destinationWarehouse?->only(['code', 'name']),
                ])->values(),
            'counts' => InventoryStockCount::query()
                ->where('counted_by_id', $user->id)
                ->with(['warehouse:id,code,name', 'lines.item:id,sku,name'])
                ->latest('counted_at')
                ->limit(10)
                ->get()
                ->map(fn (InventoryStockCount $count): array => [
                    'id' => $count->public_id,
                    'status' => $count->status,
                    'note' => $count->note,
                    'review_note' => $count->review_note,
                    'warehouse' => $count->warehouse?->only(['code', 'name']),
                    'variance' => $count->lines->map(fn ($line): array => ['item' => $line->item?->only(['sku', 'name']), 'quantity' => (string) $line->variance_quantity])->values(),
                ])->values(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function fieldDay(User $user): ?array
    {
        $fieldDay = CollectorFieldDay::query()
            ->where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        return $fieldDay instanceof CollectorFieldDay ? [
            'id' => $fieldDay->public_id,
            'status' => 'active',
            'checked_in_at' => $fieldDay->checked_in_at?->toIso8601String(),
            'checked_out_at' => null,
            'check_in' => [
                'latitude' => (float) $fieldDay->check_in_latitude,
                'longitude' => (float) $fieldDay->check_in_longitude,
                'accuracy_meters' => $fieldDay->check_in_accuracy_meters,
            ],
            'check_out' => null,
        ] : null;
    }

    /** @return array<string, mixed>|null */
    private function route(User $user, Tenant $tenant, CollectorRoutePresenter $presenter): ?array
    {
        $date = now($tenant->settingsData()->timezone)->toDateString();
        $route = CollectorRoute::query()
            ->where('user_id', $user->id)
            ->whereDate('route_date', $date)
            ->with(['stops.customer.zone', 'stops.customer.services'])
            ->first();

        return $route instanceof CollectorRoute ? $presenter->make($route) : null;
    }
}
