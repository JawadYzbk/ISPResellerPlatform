<?php

namespace App\Http\Controllers\Web;

use App\Actions\CompleteWorkOrder;
use App\Actions\ListWorkOrders;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function complete(Request $request, WorkOrder $workOrder, CompleteWorkOrder $complete): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $validated = $request->validate(['idempotency_key' => ['nullable', 'uuid']]);
        $complete->handle($workOrder, $user, $validated['idempotency_key'] ?? null);

        return redirect()->route('operations.work-orders')->with('success', "Work order {$workOrder->number} completed.");
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
