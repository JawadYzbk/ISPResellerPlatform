<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IdentifyPortalTenantFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);
        abort_unless($tenant instanceof Tenant, 400, 'A tenant context is required for customer authentication.');

        app(Tenancy::class)->set($tenant);

        try {
            $request->attributes->set('portal_tenant', $tenant);

            return $next($request);
        } finally {
            app(Tenancy::class)->clear();
        }
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $requestedSlug = trim((string) $request->header('X-Tenant-Slug'));
        if ($requestedSlug === '') {
            $requestedSlug = $this->hostSlug($request->getHost()) ?? '';
        }

        if ($requestedSlug !== '') {
            return Tenant::query()
                ->where('status', 'active')
                ->where('slug', $requestedSlug)
                ->first();
        }

        $tenants = Tenant::query()->where('status', 'active')->limit(2)->get();

        return $tenants->count() === 1 ? $tenants->first() : null;
    }

    private function hostSlug(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || in_array($host, ['localhost', 'test', 'api'], true)) {
            return null;
        }

        $parts = explode('.', strtolower($host));
        if (count($parts) < 2 || in_array($parts[0], ['www', 'api'], true)) {
            return null;
        }

        return preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $parts[0]) === 1 ? $parts[0] : null;
    }
}
