<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $locale = $user?->locale ?: $tenantLocale;
        $isPlatformOperator = $user?->isPlatformOperator() ?? false;
        $rtlLocales = ['ar', 'fa', 'he', 'ur'];
        $direction = ($settings !== null && $settings->rtl) || in_array($tenantLocale, $rtlLocales, true) || in_array($locale, $rtlLocales, true) ? 'rtl' : 'ltr';
        $hasActionFlash = $request->session()->has('success') || $request->session()->has('error');

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
                'isPlatformOperator' => $isPlatformOperator,
                'permissions' => $isPlatformOperator ? [] : ($user?->getAllPermissions()->pluck('name')->values()->all() ?? []),
                'tenant' => $tenant ? [
                    'id' => $tenant->public_id,
                    'name' => $tenant->name,
                    'currency' => $tenant->collection_currency,
                    'logo_url' => $tenant->logoUrl(),
                ] : null,
            ],
            'flash' => [
                'id' => $hasActionFlash ? (string) Str::uuid() : null,
                'successTitle' => fn () => $request->session()->get('success_title'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'importResult' => fn () => $request->session()->get('importResult'),
                'publicLink' => fn () => $request->session()->get('publicLink'),
            ],
        ];
    }
}
