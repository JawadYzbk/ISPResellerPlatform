<?php

namespace App\Http\Controllers\Web;

use App\Actions\ArchiveAddon;
use App\Actions\ArchivePlanUsageRate;
use App\Actions\ArchivePromotion;
use App\Actions\CreateAddon;
use App\Actions\CreatePlan;
use App\Actions\CreatePlanUsageRate;
use App\Actions\CreatePromotion;
use App\Actions\GetCurrencyCatalog;
use App\Actions\ListPlans;
use App\Actions\UpdateAddon;
use App\Actions\UpdatePlan;
use App\Actions\UpdatePlanUsageRate;
use App\Actions\UpdatePromotion;
use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanUsageRate;
use App\Models\Promotion;
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
    public function index(Request $request, ListPlans $listPlans, GetCurrencyCatalog $currencyCatalog): Response
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

        return Inertia::render('Plans/Index', [
            'plans' => $plans,
            'filters' => $request->only(['status', 'search']),
            'addons' => Addon::query()->orderBy('status')->orderBy('name')->get()->map(fn (Addon $addon): array => [
                'public_id' => $addon->public_id,
                'name' => $addon->name,
                'slug' => $addon->slug,
                'description' => $addon->description,
                'amount_minor' => $addon->amount_minor,
                'currency' => $addon->currency,
                'billing_period_days' => $addon->billing_period_days,
                'status' => $addon->status,
            ])->values(),
            'usageRates' => PlanUsageRate::query()->with('plan')->orderBy('status')->orderByDesc('effective_from')->get()->map(fn (PlanUsageRate $rate): array => [
                'public_id' => $rate->public_id,
                'plan_id' => $rate->plan->public_id,
                'plan_name' => $rate->plan->name,
                'name' => $rate->name,
                'metric' => $rate->metric,
                'included_bytes' => $rate->included_bytes,
                'unit_bytes' => $rate->unit_bytes,
                'amount_minor' => $rate->amount_minor,
                'currency' => $rate->currency,
                'rounding' => $rate->rounding,
                'effective_from' => $rate->effective_from->toDateString(),
                'effective_to' => $rate->effective_to?->toDateString(),
                'status' => $rate->status,
            ])->values(),
            'promotions' => Promotion::query()->orderByDesc('starts_at')->get()->map(fn (Promotion $promotion): array => [
                'public_id' => $promotion->public_id,
                'name' => $promotion->name,
                'code' => $promotion->code,
                'type' => $promotion->type,
                'value' => $promotion->value,
                'applies_to' => $promotion->applies_to ?? [],
                'starts_at' => $promotion->starts_at->toIso8601String(),
                'ends_at' => $promotion->ends_at?->toIso8601String(),
                'max_redemptions' => $promotion->max_redemptions,
                'redemptions_count' => $promotion->redemptions_count,
                'is_active' => $promotion->is_active,
            ])->values(),
            'availablePlans' => Plan::query()->where('status', 'active')->orderBy('name')->get(['public_id', 'name'])->values(),
            'currencies' => $currencyCatalog->handle(),
        ]);
    }

    public function storeAddon(Request $request, CreateAddon $create): RedirectResponse
    {
        $this->ensureManager($request);
        $data = $this->addonData($request);
        $this->ensureUniqueAddonSlug((string) $data['slug']);
        $create->handle($data);

        return redirect()->route('plans.index')->with('success', 'Addon created.');
    }

    public function updateAddon(Request $request, Addon $addon, UpdateAddon $update): RedirectResponse
    {
        $this->ensureManager($request);
        $data = $this->addonData($request);
        $this->ensureUniqueAddonSlug((string) $data['slug'], $addon);
        $update->handle($addon, $data);

        return redirect()->route('plans.index')->with('success', 'Addon updated.');
    }

    public function archiveAddon(Request $request, Addon $addon, ArchiveAddon $archive): RedirectResponse
    {
        $this->ensureManager($request);
        $archive->handle($addon);

        return redirect()->route('plans.index')->with('success', 'Addon archived.');
    }

    public function storeUsageRate(Request $request, CreatePlanUsageRate $create): RedirectResponse
    {
        $this->ensureManager($request);
        $data = $this->usageRateData($request, true);
        $plan = Plan::query()->where('public_id', $data['plan_public_id'])->firstOrFail();
        unset($data['plan_public_id']);
        $create->handle($plan, $data);

        return redirect()->route('plans.index')
            ->with('success_title', 'Usage rate created')
            ->with('success', 'The plan usage rate is ready for renewal rating.');
    }

    public function updateUsageRate(Request $request, PlanUsageRate $usageRate, UpdatePlanUsageRate $update): RedirectResponse
    {
        $this->ensureManager($request);
        $update->handle($usageRate, $this->usageRateData($request));

        return redirect()->route('plans.index')
            ->with('success_title', 'Usage rate updated')
            ->with('success', 'Future renewals will use the updated usage rate.');
    }

    public function archiveUsageRate(Request $request, PlanUsageRate $usageRate, ArchivePlanUsageRate $archive): RedirectResponse
    {
        $this->ensureManager($request);
        $archive->handle($usageRate);

        return redirect()->route('plans.index')
            ->with('success_title', 'Usage rate archived')
            ->with('success', 'The rate will no longer be selected for new renewals.');
    }

    public function storePromotion(Request $request, CreatePromotion $create): RedirectResponse
    {
        $this->ensureManager($request);
        $data = $this->promotionData($request);
        $this->ensureUniquePromotionCode((string) $data['code']);
        $create->handle($data);

        return redirect()->route('plans.index')->with('success', 'Promotion created.');
    }

    public function updatePromotion(Request $request, Promotion $promotion, UpdatePromotion $update): RedirectResponse
    {
        $this->ensureManager($request);
        $data = $this->promotionData($request);
        $this->ensureUniquePromotionCode((string) $data['code'], $promotion);
        $update->handle($promotion, $data);

        return redirect()->route('plans.index')->with('success', 'Promotion updated.');
    }

    public function archivePromotion(Request $request, Promotion $promotion, ArchivePromotion $archive): RedirectResponse
    {
        $this->ensureManager($request);
        $archive->handle($promotion);

        return redirect()->route('plans.index')->with('success', 'Promotion archived.');
    }

    public function create(Request $request, GetCurrencyCatalog $currencyCatalog): Response
    {
        abort_unless($request->user()?->can('plans.manage') === true, 403);

        return Inertia::render('Plans/Create', ['currencies' => $currencyCatalog->handle()]);
    }

    public function edit(Request $request, Plan $plan, GetCurrencyCatalog $currencyCatalog): Response
    {
        abort_unless($request->user()?->can('plans.manage') === true, 403);
        $price = $plan->priceAt();
        if ($price === null) {
            $price = $plan->prices()->latest('effective_from')->first();
        }

        return Inertia::render('Plans/Edit', [
            'plan' => [
                'public_id' => $plan->public_id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'download_kbps' => $plan->download_kbps,
                'upload_kbps' => $plan->upload_kbps,
                'duration_days' => $plan->duration_days,
                'amount_minor' => $price === null ? $plan->amount_minor : $price->amount_minor,
                'currency' => $price === null ? $plan->currency : $price->currency,
                'effective_from' => ($price === null ? now() : $price->effective_from)->toDateString(),
                'status' => $plan->status,
            ],
            'currencies' => $currencyCatalog->handle(),
        ]);
    }

    public function update(Request $request, Plan $plan, UpdatePlan $updatePlan): RedirectResponse
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
        if (Plan::query()->where('slug', $validated['slug'])->where('id', '!=', $plan->id)->exists()) {
            throw ValidationException::withMessages(['slug' => 'A plan with this slug already exists.']);
        }
        $updated = $updatePlan->handle($plan, $validated);

        return redirect()->route('plans.index')->with('success', "Plan {$updated->name} updated.");
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

    private function ensureManager(Request $request): void
    {
        abort_unless($request->user() instanceof User && $request->user()->can('plans.manage'), 403);
    }

    /** @return array<string, mixed> */
    private function addonData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_period_days' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $data['slug'] = Str::slug((string) ($data['slug'] ?: $data['name']));
        $data['currency'] = strtoupper((string) $data['currency']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function promotionData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::in(['percent', 'fixed', 'free_days'])],
            'value' => ['required', 'integer', 'min:1'],
            'applies_to' => ['nullable', 'array', 'max:50'],
            'applies_to.*' => ['string', 'max:26'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);
        if ($data['type'] === 'percent' && (int) $data['value'] > 10000) {
            throw ValidationException::withMessages(['value' => 'Percent promotions use basis points and cannot exceed 10000 (100%).']);
        }
        $data['code'] = strtoupper(trim((string) $data['code']));

        return $data;
    }

    /** @return array<string, mixed> */
    private function usageRateData(Request $request, bool $includePlan = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'included_gb' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'unit_gb' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'rounding' => ['required', Rule::in(['ceil', 'floor', 'half_up'])],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
        if ($includePlan) {
            $rules['plan_public_id'] = ['required', 'string', 'max:26'];
        }
        $data = $request->validate($rules);
        $data['included_bytes'] = $this->gigabytesToBytes((string) $data['included_gb']);
        $data['unit_bytes'] = $this->gigabytesToBytes((string) $data['unit_gb']);
        $data['metric'] = 'total_octets';
        $data['currency'] = strtoupper((string) $data['currency']);
        unset($data['included_gb'], $data['unit_gb']);

        if ($data['unit_bytes'] < 1) {
            throw ValidationException::withMessages(['unit_gb' => 'The billing unit must be greater than zero.']);
        }

        return $data;
    }

    private function gigabytesToBytes(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 9), 9, '0');

        return ((int) $whole * 1000000000) + (int) $fraction;
    }

    private function ensureUniqueAddonSlug(string $slug, ?Addon $except = null): void
    {
        $query = Addon::query()->where('slug', $slug);
        if ($except !== null) {
            $query->where('id', '!=', $except->id);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['slug' => 'An addon with this slug already exists.']);
        }
    }

    private function ensureUniquePromotionCode(string $code, ?Promotion $except = null): void
    {
        $query = Promotion::query()->whereRaw('UPPER(code) = ?', [$code]);
        if ($except !== null) {
            $query->where('id', '!=', $except->id);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => 'A promotion with this code already exists.']);
        }
    }
}
