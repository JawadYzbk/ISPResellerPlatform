<?php

namespace App\Http\Controllers\Api;

use App\Actions\CompleteWorkOrder;
use App\Actions\GetTechnicianServiceDiagnostics;
use App\Actions\ListTechnicianInventory;
use App\Actions\RecordWorkOrderReadings;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\MediaUpload;
use App\Models\Service;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TechnicianWorkOrderController extends Controller
{
    public function inventory(Request $request, ListTechnicianInventory $inventory): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        return response()->json(['data' => $inventory->handle($request->user())]);
    }

    public function diagnostics(Request $request, string $service, GetTechnicianServiceDiagnostics $diagnostics): JsonResponse
    {
        abort_unless($request->user()?->can('services.view'), 403);
        $model = Service::query()->where('public_id', $service)->firstOrFail();

        return response()->json($diagnostics->handle($model));
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureTechnician($request);
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(array_column(WorkOrderStatus::cases(), 'value'))],
        ]);
        $orders = $this->assignedQuery($request)
            ->with(['customer', 'service.plan'])
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('scheduled_at', $date))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (WorkOrder $order): array => $this->summary($order))
            ->values();

        return response()->json(['data' => $orders]);
    }

    public function show(Request $request, string $workOrder): JsonResponse
    {
        $this->ensureTechnician($request);
        $order = $this->assignedQuery($request)
            ->with(['customer', 'service.plan', 'events', 'mediaUploads', 'signature.media'])
            ->where('public_id', $workOrder)
            ->firstOrFail();

        return response()->json($this->details($order));
    }

    public function complete(Request $request, string $workOrder, CompleteWorkOrder $complete): JsonResponse
    {
        $this->ensureTechnician($request);
        $order = $this->assignedQuery($request)
            ->with('service')
            ->where('public_id', $workOrder)
            ->firstOrFail();
        $completed = $complete->handle($order, $request->user(), $request->header('X-Idempotency-Key'));
        $completed->loadMissing('service');

        return response()->json(['id' => $completed->public_id, 'status' => $completed->status->value, 'service_id' => $completed->service?->public_id], 200);
    }

    public function readings(Request $request, string $workOrder, RecordWorkOrderReadings $record): JsonResponse
    {
        $this->ensureTechnician($request);
        $validated = $request->validate([
            'readings' => ['required', 'array', 'max:20'],
            'readings.*' => ['nullable', 'string', 'max:120'],
        ]);
        $order = $this->assignedQuery($request)->where('public_id', $workOrder)->firstOrFail();
        try {
            $updated = $record->handle($order, $request->user(), array_map('strval', $validated['readings']));
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['id' => $updated->public_id, 'readings' => $updated->readings ?? []]);
    }

    private function ensureTechnician(Request $request): void
    {
        abort_unless($request->user()?->can('workorders.complete'), 403);
    }

    /** @return Builder<WorkOrder> */
    private function assignedQuery(Request $request): Builder
    {
        return WorkOrder::query()->where('assigned_to', $request->user()?->id);
    }

    /** @return array<string, mixed> */
    private function summary(WorkOrder $order): array
    {
        return [
            'id' => $order->public_id,
            'number' => $order->number,
            'type' => $order->type,
            'status' => $order->status->value,
            'scheduled_at' => $order->scheduled_at?->toIso8601String(),
            'customer' => $order->customer === null ? null : ['id' => $order->customer->public_id, 'name' => $order->customer->full_name, 'phone' => $order->customer->phone],
            'service' => $order->service === null ? null : ['id' => $order->service->public_id, 'username' => $order->service->username, 'status' => $order->service->status->value],
        ];
    }

    /** @return array<string, mixed> */
    private function details(WorkOrder $order): array
    {
        return [
            ...$this->summary($order),
            'started_at' => $order->started_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'checklist' => $order->checklist ?? [],
            'metadata' => $order->metadata ?? [],
            'readings' => $order->readings ?? [],
            'events' => $order->events->map(fn ($event): array => ['type' => $event->event_type, 'from' => $event->from_status, 'to' => $event->to_status, 'created_at' => $event->created_at?->toIso8601String()])->values(),
            'media' => $order->mediaUploads->map(fn (MediaUpload $media): array => [
                'id' => $media->public_id,
                'filename' => $media->original_name,
                'mime_type' => $media->mime_type,
                'size_bytes' => $media->size_bytes,
                'purpose' => $media->purpose,
                'created_at' => $media->created_at?->toIso8601String(),
            ])->values(),
            'signature' => $order->signature === null ? null : [
                'id' => $order->signature->id,
                'signer_name' => $order->signature->signer_name,
                'signed_at' => $order->signature->signed_at?->toIso8601String(),
                'media_id' => $order->signature->media?->public_id,
            ],
        ];
    }
}
