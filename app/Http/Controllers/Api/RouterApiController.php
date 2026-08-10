<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateRouter;
use App\Actions\ListRoutersApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRouterApiRequest;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\User;
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

    public function store(CreateRouterApiRequest $request, CreateRouter $createRouter, RouterApiResource $resource): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.provision') && $user->tenant instanceof Tenant, 403);
        $router = $createRouter->handle($request->validated(), $user->tenant);

        return response()->json($resource->make($router), 201);
    }

    public function show(Request $request, string $router, RouterApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('network.view'), 403);
        $model = Router::query()->where('public_id', $router)->firstOrFail();

        return response()->json($resource->make($model));
    }
}
