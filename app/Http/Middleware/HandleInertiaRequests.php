<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;
        $tenant = $user instanceof User ? Tenant::query()->find($user->tenant_id) : null;
        $tenant = $tenant instanceof Tenant ? $tenant : null;
        $settings = $tenant instanceof Tenant ? $tenant->settingsData() : null;
        $tenantLocale = $settings === null ? 'en' : $settings->locale;
        $locale = $user instanceof User && $user->locale !== null ? $user->locale : $tenantLocale;
        $rtlLocales = ['ar', 'fa', 'he', 'ur'];
        $direction = ($settings !== null && $settings->rtl) || in_array($tenantLocale, $rtlLocales, true) || in_array($locale, $rtlLocales, true) ? 'rtl' : 'ltr';

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'locale' => $locale,
                'direction' => $direction,
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
                'permissions' => $user?->getAllPermissions()->pluck('name')->values()->all() ?? [],
                'tenant' => $tenant ? [
                    'id' => $tenant->public_id,
                    'name' => $tenant->name,
                    'currency' => $tenant->collection_currency,
                    'logo_url' => $tenant->logo_path === null ? null : route('tenant.logo', $tenant),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'importResult' => fn () => $request->session()->get('importResult'),
            ],
        ];
    }
}
