<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateTenant;
use App\Actions\GetCurrencyCatalog;
use App\Actions\RecordPlatformAudit;
use App\Actions\UpdatePlatformTenant;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformTenantController extends Controller
{
    public function index(GetCurrencyCatalog $currencyCatalog): Response
    {
        $tenants = Tenant::query()
            ->withCount(['users', 'customers', 'services'])
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'slug', 'status', 'locale', 'timezone', 'base_currency', 'collection_currency', 'created_at'])
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->public_id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'locale' => $tenant->locale,
                'timezone' => $tenant->timezone,
                'base_currency' => $tenant->base_currency,
                'collection_currency' => $tenant->collection_currency,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'users_count' => $tenant->users_count,
                'customers_count' => $tenant->customers_count,
                'services_count' => $tenant->services_count,
            ])
            ->values();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'currencies' => $currencyCatalog->handle(),
        ]);
    }

    public function store(Request $request, GetCurrencyCatalog $currencyCatalog, CreateTenant $create, RecordPlatformAudit $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $codes = collect($currencyCatalog->handle())->pluck('code')->all();
        $validated = $request->validate($this->createRules($codes));
        $tenant = $create->handle($validated, $actor, $audit);

        return redirect()->route('admin.tenants')->with('success_title', 'Tenant created')->with('success', "Workspace {$tenant->name} is ready.");
    }

    public function update(Request $request, Tenant $tenant, UpdatePlatformTenant $update, RecordPlatformAudit $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(['active', 'suspended', 'archived'])],
        ]);
        $updated = $update->handle($tenant, $validated, $actor, $audit);

        return redirect()->route('admin.tenants')->with('success_title', 'Tenant updated')->with('success', "Workspace {$updated->name} was updated.");
    }

    /** @param list<string> $codes */
    private function createRules(array $codes): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash:ascii', 'unique:tenants,slug'],
            'locale' => ['required', Rule::in(['en', 'ar', 'fr'])],
            'timezone' => ['required', 'timezone'],
            'base_currency' => ['required', 'string', 'size:3', Rule::in($codes)],
            'collection_currency' => ['required', 'string', 'size:3', Rule::in($codes)],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isPlatformOperator(), 403);

        return $user;
    }
}
