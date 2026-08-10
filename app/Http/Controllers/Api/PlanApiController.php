<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreatePlan;
use App\Actions\ListPlansApi;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Api\PlanApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PlanApiController extends Controller
{
    public function index(Request $request, ListPlansApi $listPlans): JsonResponse
    {
        abort_unless($request->user()?->can('plans.manage'), 403);

        return response()->json($listPlans->handle($request, $request->integer('per_page', 20)));
    }

    public function show(Request $request, string $plan, PlanApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('plans.manage'), 403);
        $model = Plan::query()->where('public_id', $plan)->firstOrFail();

        return response()->json($resource->make($model));
    }

    public function store(Request $request, CreatePlan $createPlan, PlanApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('plans.manage'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'download_kbps' => ['required', 'integer', 'min:0'],
            'upload_kbps' => ['required', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $validated['slug'] = Str::slug((string) ($validated['slug'] ?? $validated['name']));
        $validated['currency'] = strtoupper((string) $validated['currency']);
        if (Plan::query()->where('slug', $validated['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'A plan with this slug already exists.']);
        }

        return response()->json($resource->make($createPlan->handle($validated)), 201);
    }
}
