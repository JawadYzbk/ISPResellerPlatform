<?php

namespace App\Http\Middleware;

use App\Models\PortalSession;
use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticatePortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $tenancy = app(Tenancy::class);
        $hasTenantContext = $tenancy->id() !== null;
        $sessions = $hasTenantContext ? PortalSession::query() : PortalSession::withoutGlobalScopes();
        $session = $token === null ? null : $sessions->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($session instanceof PortalSession, 401, 'A valid portal session is required.');

        $tenantContextStartedHere = false;
        if (! $hasTenantContext) {
            $tenant = Tenant::query()->where('status', 'active')->find($session->tenant_id);
            abort_unless($tenant instanceof Tenant, 401, 'A valid portal session is required.');
            $tenancy->set($tenant);
            $tenantContextStartedHere = true;
        }

        $session->load('customer');
        $session->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('portal_session', $session);
        $request->attributes->set('portal_customer', $session->customer);

        try {
            return $next($request);
        } finally {
            if ($tenantContextStartedHere) {
                $tenancy->clear();
            }
        }
    }
}
