<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetWorkOrderDetails;
use App\Actions\ListWorkOrdersApi;
use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Support\Api\WorkOrderApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkOrderApiController extends Controller
{
    public function index(Request $request, ListWorkOrdersApi $listWorkOrders): JsonResponse
    {
        abort_unless($request->user()?->can('workorders.complete'), 403);

        return response()->json($listWorkOrders->handle($request, $request->integer('per_page', 20)));
    }

    public function show(Request $request, string $workOrder, GetWorkOrderDetails $getDetails, WorkOrderApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('workorders.complete'), 403);
        $model = WorkOrder::query()->where('public_id', $workOrder)->firstOrFail();

        return response()->json($resource->make($getDetails->handle($model)));
    }
}
