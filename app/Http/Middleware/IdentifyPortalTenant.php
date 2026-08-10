<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IdentifyPortalTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::query()->where('slug', $tenant)->firstOrFail();
        app(Tenancy::class)->set($tenant);

        try {
            return $next($request);
        } finally {
            app(Tenancy::class)->clear();
        }
    }
}
