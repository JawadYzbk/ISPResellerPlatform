<?php

namespace App\Http\Controllers\Api;

use App\Actions\ImportRouterSubscribers;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RouterSubscriberImportController extends Controller
{
    public function store(Request $request, int $router, ImportRouterSubscribers $import): JsonResponse
    {
        abort_unless($request->user()?->can('network.view'), 403);
        $validated = $request->validate(['dry_run' => ['sometimes', 'boolean']]);
        $model = Router::query()->whereKey($router)->firstOrFail();
        $batch = $import->handle($model, (bool) ($validated['dry_run'] ?? false));

        return response()->json($this->payload($batch), $batch->status === 'completed' ? 201 : 200);
    }

    /** @return array<string, mixed> */
    private function payload(ImportBatch $batch): array
    {
        return [
            'id' => $batch->public_id,
            'type' => $batch->type,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'successful_rows' => $batch->successful_rows,
            'failed_rows' => $batch->failed_rows,
            'report' => $batch->report ?? [],
        ];
    }
}
