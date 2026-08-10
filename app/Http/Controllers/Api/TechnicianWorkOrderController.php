<?php

namespace App\Http\Controllers\Api;

use App\Actions\CompleteWorkOrder;
use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TechnicianWorkOrderController extends Controller
{
    public function complete(Request $request, int $workOrder, CompleteWorkOrder $complete): JsonResponse
    {
        abort_unless($request->user()?->can('workorders.complete'), 403);
        $order = WorkOrder::query()->with('service')->findOrFail($workOrder);
        $completed = $complete->handle($order, $request->user(), $request->header('X-Idempotency-Key'));

        return response()->json(['id' => $completed->public_id, 'status' => $completed->status->value, 'service_id' => $completed->service_id], 200);
    }
}
