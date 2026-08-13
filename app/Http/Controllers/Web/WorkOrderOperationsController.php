<?php

namespace App\Http\Controllers\Web;

use App\Actions\AcceptWorkOrderInstallation;
use App\Actions\CaptureWorkOrderSignature;
use App\Actions\CompleteWorkOrder;
use App\Actions\ConsumeWorkOrderMaterial;
use App\Actions\GetWorkOrderDetails;
use App\Actions\ListBulkStock;
use App\Actions\ListWorkOrderCalendar;
use App\Actions\ListWorkOrders;
use App\Actions\RecordWorkOrderReadings;
use App\Actions\SaveWorkOrderInstallation;
use App\Actions\ScheduleWorkOrder;
use App\Http\Controllers\Controller;
use App\Models\DistributionBox;
use App\Models\InventoryItem;
use App\Models\MediaUpload;
use App\Models\NetworkBuilding;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderMaterial;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class WorkOrderOperationsController extends Controller
{
    public function index(Request $request, ListWorkOrders $listWorkOrders): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $orders = $listWorkOrders->handle(
            $request->string('status')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );
        $rows = $orders->getCollection()->map(function (mixed $order): array {
            if (! $order instanceof WorkOrder) {
                throw new \LogicException('Work-order paginator contained an invalid record.');
            }

            return [
                'public_id' => $order->public_id,
                'number' => $order->number,
                'type' => $order->type,
                'status' => $order->status->value,
                'scheduled_at' => $this->isoDate($order->scheduled_at),
                'started_at' => $this->isoDate($order->started_at),
                'completed_at' => $this->isoDate($order->completed_at),
                'checklist' => $order->checklist ?? [],
                'customer' => $order->customer === null ? null : [
                    'public_id' => $order->customer->public_id,
                    'code' => $order->customer->code,
                    'name' => $order->customer->full_name,
                ],
                'service' => $order->service === null ? null : [
                    'public_id' => $order->service->public_id,
                    'username' => $order->service->username,
                ],
                'assignee' => $order->assignee === null ? null : ['name' => $order->assignee->name],
            ];
        })->values();
        $orders = new LengthAwarePaginator(
            $rows,
            $orders->total(),
            $orders->perPage(),
            $orders->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Operations/WorkOrders', [
            'workOrders' => $orders,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function calendar(Request $request, ListWorkOrderCalendar $listWorkOrderCalendar): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate(['week' => ['nullable', 'date_format:Y-m-d']]);
        $timezone = $this->tenantTimezone($user);
        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) ($validated['week'] ?? now($timezone)->toDateString()), $timezone)->startOfWeek();
        $orders = $listWorkOrderCalendar->handle($weekStart, $timezone);

        return Inertia::render('Operations/WorkOrderCalendar', [
            'weekStart' => $weekStart->toDateString(),
            'timezone' => $timezone,
            'workOrders' => $orders->map(fn (WorkOrder $order): array => [
                'public_id' => $order->public_id,
                'number' => $order->number,
                'type' => $order->type,
                'status' => $order->status->value,
                'scheduled_at' => $this->isoDate($order->scheduled_at),
                'scheduled_at_local' => $order->scheduled_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'customer' => $order->customer === null ? null : ['public_id' => $order->customer->public_id, 'name' => $order->customer->full_name],
                'assignee' => $order->assignee?->name,
            ])->values(),
        ]);
    }

    public function complete(Request $request, WorkOrder $workOrder, CompleteWorkOrder $complete): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate(['idempotency_key' => ['nullable', 'uuid']]);
        try {
            $complete->handle($workOrder, $user, $validated['idempotency_key'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['completion' => $exception->getMessage()]);
        }

        return redirect()->route('operations.work-orders')->with('success', "Work order {$workOrder->number} completed.");
    }

    public function saveInstallation(Request $request, WorkOrder $workOrder, SaveWorkOrderInstallation $save): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate([
            'network_building_id' => ['required', 'integer'],
            'distribution_box_id' => ['required', 'string'],
            'network_port' => ['required', 'integer', 'min:1'],
            'onu_serial' => ['nullable', 'string', 'max:128'],
            'survey' => ['nullable', 'array', 'max:20'],
            'survey.*' => ['nullable', 'string', 'max:500'],
        ]);
        $box = DistributionBox::query()->where('public_id', $validated['distribution_box_id'])->firstOrFail();
        abort_unless((int) $box->network_building_id === (int) $validated['network_building_id'], 422, 'The selected box does not belong to the selected building.');

        try {
            $save->handle(
                $workOrder,
                $box,
                (int) $validated['network_port'],
                array_map('strval', $validated['survey'] ?? []),
                $validated['onu_serial'] ?? null,
                $user,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['installation' => $exception->getMessage()]);
        }

        return redirect()->route('operations.work-orders.show', $workOrder->public_id)->with('success', 'Installation details saved.');
    }

    public function acceptActivation(Request $request, WorkOrder $workOrder, AcceptWorkOrderInstallation $accept): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $accept->handle($workOrder, $user, $validated['note'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['activation' => $exception->getMessage()]);
        }

        return redirect()->route('operations.work-orders.show', $workOrder->public_id)->with('success', 'Activation accepted.');
    }

    public function schedule(Request $request, WorkOrder $workOrder, ScheduleWorkOrder $schedule): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'context' => ['nullable', 'string', 'in:calendar'],
        ]);
        $timezone = $this->tenantTimezone($user);
        try {
            $schedule->handle($workOrder, $user, CarbonImmutable::parse((string) $validated['scheduled_at'], $timezone)->utc());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['scheduled_at' => $exception->getMessage()]);
        }

        if (($validated['context'] ?? null) === 'calendar') {
            return redirect()
                ->route('operations.work-orders.calendar', [
                    'week' => CarbonImmutable::parse((string) $validated['scheduled_at'], $timezone)->startOfWeek()->toDateString(),
                ])
                ->with('success', "Work order {$workOrder->number} scheduled.");
        }

        return redirect()->route('operations.work-orders.show', $workOrder->public_id)->with('success', "Work order {$workOrder->number} scheduled.");
    }

    public function storeSignature(Request $request, WorkOrder $workOrder, CaptureWorkOrderSignature $capture): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimetypes:image/png'],
            'signer_name' => ['required', 'string', 'max:120'],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'A signature file is required.');
        try {
            $capture->handle($workOrder, $user, $file, (string) $validated['signer_name']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['signer_name' => $exception->getMessage()]);
        }

        return redirect()->route('operations.work-orders.show', $workOrder->public_id)->with('success', 'Work-order signature captured.');
    }

    public function readings(Request $request, WorkOrder $workOrder, RecordWorkOrderReadings $record): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate([
            'readings' => ['required', 'array', 'max:20'],
            'readings.*' => ['nullable', 'string', 'max:120'],
        ]);
        try {
            $record->handle($workOrder, $user, array_map('strval', $validated['readings']));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['readings' => $exception->getMessage()]);
        }

        return redirect()->route('operations.work-orders.show', $workOrder->public_id)->with('success', 'Work-order readings saved.');
    }

    public function material(Request $request, WorkOrder $workOrder, ConsumeWorkOrderMaterial $consume): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $item = InventoryItem::query()->findOrFail($validated['inventory_item_id']);
        $warehouse = Warehouse::query()->findOrFail($validated['warehouse_id']);
        try {
            $consume->handle($workOrder, $item, $warehouse, $user, (string) $validated['quantity'], $validated['note'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        return redirect()->route('operations.work-orders.show', $workOrder->public_id)->with('success', 'Work-order material recorded.');
    }

    public function show(Request $request, WorkOrder $workOrder, GetWorkOrderDetails $getDetails, ListBulkStock $listBulkStock): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $workOrder = $getDetails->handle($workOrder);
        $workOrder->loadMissing(['networkBuilding', 'distributionBox', 'activationAcceptedBy']);
        $timezone = $this->tenantTimezone($user);
        $installationEnabled = in_array($workOrder->type, ['installation', 'fiber'], true);
        $networkBuildings = $installationEnabled
            ? NetworkBuilding::query()->with(['distributionBoxes' => fn ($query) => $query->where('status', 'active')->orderBy('name')])->orderBy('name')->get()
            : collect();

        return Inertia::render('Operations/WorkOrderShow', [
            'workOrder' => [
                'public_id' => $workOrder->public_id,
                'number' => $workOrder->number,
                'type' => $workOrder->type,
                'status' => $workOrder->status->value,
                'scheduled_at' => $this->isoDate($workOrder->scheduled_at),
                'started_at' => $this->isoDate($workOrder->started_at),
                'completed_at' => $this->isoDate($workOrder->completed_at),
                'failure_reason' => $workOrder->failure_reason,
                'checklist' => $workOrder->checklist ?? [],
                'metadata' => $workOrder->metadata ?? [],
                'readings' => $workOrder->readings ?? [],
                'installation' => [
                    'enabled' => $installationEnabled,
                    'requires_acceptance' => (bool) ($workOrder->metadata['requires_installation_acceptance'] ?? false),
                    'network_building_id' => $workOrder->network_building_id,
                    'distribution_box_id' => $workOrder->distribution_box_id,
                    'network_building_code' => $workOrder->networkBuilding?->code,
                    'distribution_box_public_id' => $workOrder->distributionBox?->public_id,
                    'distribution_box_code' => $workOrder->distributionBox?->code,
                    'network_port' => $workOrder->network_port,
                    'onu_serial' => $workOrder->onu_serial,
                    'survey' => $workOrder->installation_survey ?? [],
                    'activation_accepted_at' => $workOrder->activation_accepted_at?->toIso8601String(),
                    'activation_accepted_by' => $workOrder->activationAcceptedBy?->name,
                    'activation_acceptance_note' => $workOrder->activation_acceptance_note,
                ],
                'customer' => $workOrder->customer === null ? null : ['public_id' => $workOrder->customer->public_id, 'code' => $workOrder->customer->code, 'name' => $workOrder->customer->full_name],
                'service' => $workOrder->service === null ? null : ['public_id' => $workOrder->service->public_id, 'username' => $workOrder->service->username],
                'assignee' => $workOrder->assignee === null ? null : ['name' => $workOrder->assignee->name],
                'events' => $workOrder->events->map(fn ($event): array => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'actor' => $event->actor?->name,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])->values(),
                'media' => $workOrder->mediaUploads->map(fn (MediaUpload $media): array => [
                    'id' => $media->public_id,
                    'filename' => $media->original_name,
                    'mime_type' => $media->mime_type,
                    'size_bytes' => $media->size_bytes,
                    'purpose' => $media->purpose,
                    'created_at' => $media->created_at?->toIso8601String(),
                    'download_url' => route('operations.media.download', $media->public_id),
                ])->values(),
                'signature' => $workOrder->signature === null ? null : [
                    'id' => $workOrder->signature->id,
                    'signer_name' => $workOrder->signature->signer_name,
                    'signed_at' => $workOrder->signature->signed_at?->toIso8601String(),
                    'download_url' => $workOrder->signature->media === null ? null : route('operations.media.download', $workOrder->signature->media->public_id),
                ],
                'materials' => $workOrder->materials->map(fn (WorkOrderMaterial $material): array => [
                    'id' => $material->id,
                    'sku' => $material->item?->sku,
                    'name' => $material->item?->name,
                    'warehouse' => $material->warehouse?->code,
                    'quantity' => (string) $material->quantity,
                    'note' => $material->note,
                    'consumed_at' => $material->consumed_at?->toIso8601String(),
                ])->values(),
            ],
            'bulkMaterials' => $listBulkStock->handle()->map(fn ($balance): array => [
                'inventory_item_id' => $balance->inventory_item_id,
                'warehouse_id' => $balance->warehouse_id,
                'sku' => $balance->item?->sku,
                'name' => $balance->item?->name,
                'warehouse' => $balance->warehouse?->code,
                'quantity' => (string) $balance->quantity,
            ])->values(),
            'scheduledAtLocal' => $this->localDate($workOrder->scheduled_at, $timezone),
            'timezone' => $timezone,
            'canManageInstallation' => $user->can('workorders.complete'),
            'networkBuildings' => $networkBuildings->map(fn (NetworkBuilding $building): array => [
                'id' => $building->id,
                'name' => $building->name,
                'code' => $building->code,
                'boxes' => $building->distributionBoxes->map(fn (DistributionBox $box): array => [
                    'id' => $box->id,
                    'public_id' => $box->public_id,
                    'name' => $box->name,
                    'code' => $box->code,
                    'capacity_ports' => $box->capacity_ports,
                ])->values(),
            ])->values(),
        ]);
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }

    private function localDate(mixed $value, string $timezone): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->setTimezone($timezone)->format('Y-m-d\TH:i');
    }

    private function tenantTimezone(User $user): string
    {
        $tenant = $user->tenant()->first();

        return $tenant instanceof Tenant && filled($tenant->timezone) ? $tenant->timezone : 'UTC';
    }
}
