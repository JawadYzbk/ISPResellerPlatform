<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user()?->tenant;

        abort_unless($tenant, 403, 'A tenant membership is required.');

        $tenancy = app(Tenancy::class);
        $tenancy->set($tenant);

        try {
            return $next($request);
        } finally {
            $tenancy->clear();
        }
    }
}
