<?php

namespace App\Http\Controllers\Api;

use App\Actions\ImportPlansCsv;
use App\Actions\RollbackImport;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlanImportController extends Controller
{
    public function store(Request $request, ImportPlansCsv $import): JsonResponse
    {
        abort_unless($request->user()?->can('plans.manage'), 403);
        $validated = $request->validate([
            'filename' => ['nullable', 'string', 'max:255'],
            'csv' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        $contents = isset($validated['file']) ? $validated['file']->get() : ($validated['csv'] ?? null);
        abort_if($contents === null, 422, 'Provide either csv text or a file upload.');
        $filename = (string) ($validated['filename'] ?? $validated['file']?->getClientOriginalName() ?? 'plans.csv');
        $tenant = Tenant::query()->findOrFail(app(Tenancy::class)->requireId());
        $batch = $import->handle($tenant, $contents, $filename, (bool) ($validated['dry_run'] ?? false));

        return response()->json($this->payload($batch), $batch->status === 'completed' ? 201 : 200);
    }

    public function rollback(Request $request, string $import, RollbackImport $rollback): JsonResponse
    {
        abort_unless($request->user()?->can('plans.manage'), 403);
        $batch = ImportBatch::query()->where('public_id', $import)->where('type', 'plans')->firstOrFail();
        $deleted = $rollback->handle($batch);

        return response()->json(['id' => $batch->public_id, 'status' => $batch->refresh()->status, 'deleted_plans' => $deleted]);
    }

    /** @return array<string, mixed> */
    private function payload(ImportBatch $batch): array
    {
        return [
            'id' => $batch->public_id,
            'type' => $batch->type,
            'filename' => $batch->filename,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'successful_rows' => $batch->successful_rows,
            'failed_rows' => $batch->failed_rows,
            'report' => $batch->report ?? [],
        ];
    }
}
