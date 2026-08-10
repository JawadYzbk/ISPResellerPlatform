<?php

namespace App\Http\Controllers\Api;

use App\Actions\ImportRouterSubscribers;
use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Support\Api\ImportBatchApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RouterSubscriberImportController extends Controller
{
    public function store(Request $request, string $router, ImportRouterSubscribers $import, ImportBatchApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('network.view'), 403);
        $validated = $request->validate(['dry_run' => ['sometimes', 'boolean']]);
        $model = Router::query()->where('public_id', $router)->firstOrFail();
        $batch = $import->handle($model, (bool) ($validated['dry_run'] ?? false));

        return response()->json($resource->make($batch), $batch->status === 'completed' ? 201 : 200);
    }
}
