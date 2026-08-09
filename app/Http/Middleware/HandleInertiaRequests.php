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
        $tenant = $user?->tenant;
        $tenant = $tenant instanceof Tenant ? $tenant : null;
        $locale = 'en';
        if ($user !== null && $user->locale !== null) {
            $locale = $user->locale;
        } elseif ($tenant !== null) {
            $locale = $tenant->locale;
        }

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
