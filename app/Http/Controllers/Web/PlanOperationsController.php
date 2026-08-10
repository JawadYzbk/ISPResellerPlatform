<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreatePlan;
use App\Actions\ListPlans;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PlanOperationsController extends Controller
{
    public function index(Request $request, ListPlans $listPlans): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('plans.manage'), 403);
        $plans = $listPlans->handle(
            $request->string('status')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );
        $rows = $plans->getCollection()->map(function (mixed $plan): array {
            if (! $plan instanceof Plan) {
                throw new \LogicException('Plan paginator contained an invalid record.');
            }
            $price = $plan->prices->first();

            return [
                'public_id' => $plan->public_id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'status' => $plan->status,
                'download_kbps' => $plan->download_kbps,
                'upload_kbps' => $plan->upload_kbps,
                'duration_days' => $plan->duration_days,
                'services_count' => $plan->services_count,
                'price' => $price === null ? null : ['amount_minor' => $price->amount_minor, 'currency' => $price->currency, 'effective_from' => $price->effective_from->toIso8601String()],
            ];
        })->values();
        $plans = new LengthAwarePaginator(
            $rows,
            $plans->total(),
            $plans->perPage(),
            $plans->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Plans/Index', ['plans' => $plans, 'filters' => $request->only(['status', 'search'])]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('plans.manage') === true, 403);

        return Inertia::render('Plans/Create');
    }

    public function store(Request $request, CreatePlan $createPlan): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('plans.manage'), 403);
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
        $validated['slug'] = Str::slug((string) ($validated['slug'] ?: $validated['name']));
        $validated['currency'] = strtoupper($validated['currency']);
        if (Plan::query()->where('slug', $validated['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'A plan with this slug already exists.']);
        }
        $plan = $createPlan->handle($validated);

        return redirect()->route('plans.index')->with('success', "Plan {$plan->name} created.");
    }
}
