<?php

namespace App\Http\Controllers\Api;

use App\Actions\ImportServicesCsv;
use App\Actions\NormalizeTabularImport;
use App\Actions\RollbackImport;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\Tenant;
use App\Support\Api\ImportBatchApiResource;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceImportController extends Controller
{
    public function store(Request $request, ImportServicesCsv $import, NormalizeTabularImport $normalize, ImportBatchApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('services.create'), 403);
        $validated = $request->validate([
            'filename' => ['nullable', 'string', 'max:255'],
            'csv' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        $contents = isset($validated['file']) ? $validated['file']->get() : ($validated['csv'] ?? null);
        abort_if($contents === null, 422, 'Provide either csv text or a file upload.');
        $filename = (string) ($validated['filename'] ?? $validated['file']?->getClientOriginalName() ?? 'services.csv');
        $contents = $normalize->handle($contents, $filename);
        $tenant = Tenant::query()->findOrFail(app(Tenancy::class)->requireId());
        $batch = $import->handle($tenant, $contents, $filename, (bool) ($validated['dry_run'] ?? false));

        return response()->json($resource->make($batch), $batch->status === 'completed' ? 201 : 200);
    }

    public function rollback(Request $request, string $import, RollbackImport $rollback): JsonResponse
    {
        abort_unless($request->user()?->can('services.create'), 403);
        $batch = ImportBatch::query()->where('public_id', $import)->where('type', 'services')->firstOrFail();
        $deleted = $rollback->handle($batch);

        return response()->json(['id' => $batch->public_id, 'status' => $batch->refresh()->status, 'deleted_services' => $deleted]);
    }
}
