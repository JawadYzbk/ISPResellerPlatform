<?php

namespace App\Http\Controllers\Web;

use App\Actions\CompleteWorkOrder;
use App\Actions\GetWorkOrderDetails;
use App\Actions\ListWorkOrderCalendar;
use App\Actions\ListWorkOrders;
use App\Actions\ScheduleWorkOrder;
use App\Http\Controllers\Controller;
use App\Models\MediaUpload;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $complete->handle($workOrder, $user, $validated['idempotency_key'] ?? null);

        return redirect()->route('operations.work-orders')->with('success', "Work order {$workOrder->number} completed.");
    }

    public function schedule(Request $request, WorkOrder $workOrder, ScheduleWorkOrder $schedule): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate(['scheduled_at' => ['required', 'date']]);
        $timezone = $this->tenantTimezone($user);
        try {
            $schedule->handle($workOrder, $user, CarbonImmutable::parse((string) $validated['scheduled_at'], $timezone)->utc());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['scheduled_at' => $exception->getMessage()]);
        }

        return redirect()->route('operations.work-orders.show', $workOrder->public_id)->with('success', "Work order {$workOrder->number} scheduled.");
    }

    public function show(Request $request, WorkOrder $workOrder, GetWorkOrderDetails $getDetails): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $workOrder = $getDetails->handle($workOrder);
        $timezone = $this->tenantTimezone($user);

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
            ],
            'scheduledAtLocal' => $this->localDate($workOrder->scheduled_at, $timezone),
            'timezone' => $timezone,
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
