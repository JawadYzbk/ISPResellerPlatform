<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class LocationOperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->settingsUser($request);
        $branches = Branch::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'address', 'phone', 'is_default'])
            ->values();
        $zones = Zone::query()
            ->with('parent:id,name')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'code'])
            ->map(fn (Zone $zone): array => [
                'id' => $zone->id,
                'parent_id' => $zone->parent_id,
                'parent_name' => $zone->parent?->name,
                'name' => $zone->name,
                'code' => $zone->code,
            ])
            ->values();

        return Inertia::render('Settings/Locations', [
            'branches' => $branches,
            'zones' => $zones,
            'tenant' => $user->tenant?->only(['name', 'slug']),
        ]);
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $user = $this->settingsUser($request);
        $this->normalizeCode($request);
        $validated = $request->validate($this->branchRules($user->tenant_id));

        DB::transaction(function () use ($validated, $user): void {
            $isFirst = ! Branch::query()->exists();
            $isDefault = $isFirst || (bool) ($validated['is_default'] ?? false);
            if ($isDefault) {
                Branch::query()->update(['is_default' => false]);
            }

            Branch::create([
                ...$validated,
                'tenant_id' => $user->tenant_id,
                'is_default' => $isDefault,
            ]);
        });

        return redirect()->route('settings.locations')->with('success', 'Branch created.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $user = $this->settingsUser($request);
        $this->assertTenantRecord($branch, $user);
        $this->normalizeCode($request);
        $validated = $request->validate($this->branchRules($user->tenant_id, $branch));

        DB::transaction(function () use ($branch, $validated): void {
            $isDefault = (bool) ($validated['is_default'] ?? false);
            if (! $isDefault && $branch->is_default) {
                $replacement = Branch::query()->whereKeyNot($branch->id)->orderBy('name')->first();
                if (! $replacement instanceof Branch) {
                    throw ValidationException::withMessages(['is_default' => 'Keep at least one default branch configured.']);
                }

                $replacement->forceFill(['is_default' => true])->save();
            }

            if ($isDefault) {
                Branch::query()->whereKeyNot($branch->id)->update(['is_default' => false]);
            }

            $branch->update($validated);
        });

        return redirect()->route('settings.locations')->with('success', 'Branch updated.');
    }

    public function storeZone(Request $request): RedirectResponse
    {
        $user = $this->settingsUser($request);
        $this->normalizeCode($request);
        $validated = $request->validate($this->zoneRules($user->tenant_id));

        Zone::create([...$validated, 'tenant_id' => $user->tenant_id]);

        return redirect()->route('settings.locations')->with('success', 'Service zone created.');
    }

    public function updateZone(Request $request, Zone $zone): RedirectResponse
    {
        $user = $this->settingsUser($request);
        $this->assertTenantRecord($zone, $user);
        $this->normalizeCode($request);
        $validated = $request->validate($this->zoneRules($user->tenant_id, $zone));
        if ((int) ($validated['parent_id'] ?? 0) === $zone->id) {
            throw ValidationException::withMessages(['parent_id' => 'A service zone cannot be its own parent.']);
        }

        $zone->update($validated);

        return redirect()->route('settings.locations')->with('success', 'Service zone updated.');
    }

    /** @return array<string, array<int, mixed>> */
    private function branchRules(int $tenantId, ?Branch $branch = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('branches', 'code')->where('tenant_id', $tenantId)->ignore($branch?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function zoneRules(int $tenantId, ?Zone $zone = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('zones', 'code')->where('tenant_id', $tenantId)->ignore($zone?->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('zones', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    private function settingsUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage') && $user->tenant instanceof Tenant, 403);

        return $user;
    }

    private function assertTenantRecord(Branch|Zone $record, User $user): void
    {
        abort_unless($record->tenant_id === $user->tenant_id, 404);
    }

    private function normalizeCode(Request $request): void
    {
        $request->merge(['code' => strtoupper(trim($request->string('code')->toString()))]);
    }
}
