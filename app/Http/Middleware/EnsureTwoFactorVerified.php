<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Security\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! $user->requiresTwoFactor() || $request->routeIs('two-factor.*')) {
            return $next($request);
        }

        $twoFactor = app(TwoFactorService::class);
        if (! $twoFactor->enabled($user)) {
            return $request->expectsJson()
                ? abort(423, 'Two-factor authentication must be configured.')
                : redirect()->route('two-factor.setup');
        }

        if ((int) $request->session()->get('two_factor_verified_user_id') !== $user->id) {
            return $request->expectsJson()
                ? abort(423, 'Two-factor authentication is required.')
                : redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
