<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = $user?->tenant;
        $locale = $user?->locale ?? $tenant?->locale ?? 'en';

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'locale' => $locale,
                'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
                'tenant' => $tenant ? [
                    'id' => $tenant->public_id,
                    'name' => $tenant->name,
                    'currency' => $tenant->collection_currency,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
