<?php

namespace App\Http\Controllers\Api;

use App\Actions\ListZonesApi;
use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Support\Api\ZoneApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ZoneApiController extends Controller
{
    public function index(Request $request, ListZonesApi $listZones): JsonResponse
    {
        abort_unless($request->user()?->can('customers.view'), 403);

        return response()->json($listZones->handle($request, $request->integer('per_page', 20)));
    }

    public function show(Request $request, int $zone, ZoneApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('customers.view'), 403);
        $model = Zone::query()->whereKey($zone)->firstOrFail();

        return response()->json($resource->make($model));
    }
}
