<?php

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRecentAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->last_authenticated_at !== null && CarbonImmutable::parse((string) $user->last_authenticated_at)->greaterThanOrEqualTo(now()->subMinutes(10))) {
            return $next($request);
        }

        return $request->expectsJson()
            ? abort(401, 'Recent authentication is required.')
            : redirect()->route('security.reauthenticate');
    }
}
