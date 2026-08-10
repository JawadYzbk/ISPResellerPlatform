<?php

namespace App\Http\Controllers\Api;

use App\Actions\ListRoutersApi;
use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Support\Api\RouterApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RouterApiController extends Controller
{
    public function index(Request $request, ListRoutersApi $listRouters): JsonResponse
    {
        abort_unless($request->user()?->can('network.view'), 403);

        return response()->json($listRouters->handle($request, $request->integer('per_page', 20)));
    }

    public function show(Request $request, string $router, RouterApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('network.view'), 403);
        $model = Router::query()->where('public_id', $router)->firstOrFail();

        return response()->json($resource->make($model));
    }
}
