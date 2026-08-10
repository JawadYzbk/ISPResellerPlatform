<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Support\RequestContext;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $tenant = $user->tenant;

        abort_unless($tenant instanceof Tenant, 403, 'A tenant membership is required.');

        $tenancy = app(Tenancy::class);
        $tenancy->set($tenant);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        app(RequestContext::class)->add(['tenant_id' => $tenant->id]);
        Log::withContext(['tenant_id' => $tenant->id]);

        try {
            return $next($request);
        } finally {
            $tenancy->clear();
        }
    }
}
