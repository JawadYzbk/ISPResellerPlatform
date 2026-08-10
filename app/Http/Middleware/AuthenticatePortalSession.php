<?php

namespace App\Http\Middleware;

use App\Models\PortalSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticatePortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $session = $token === null ? null : PortalSession::query()->with('customer')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($session instanceof PortalSession, 401, 'A valid portal session is required.');
        $session->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('portal_session', $session);
        $request->attributes->set('portal_customer', $session->customer);

        return $next($request);
    }
}
