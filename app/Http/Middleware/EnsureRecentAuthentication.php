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

        if ($request->expectsJson()) {
            abort(401, 'Recent authentication is required.');
        }

        $this->rememberIntendedUrl($request);

        return redirect()->route('security.reauthenticate');
    }

    private function rememberIntendedUrl(Request $request): void
    {
        $intended = $request->isMethodSafe()
            ? $request->fullUrl()
            : $request->headers->get('referer');

        if (! is_string($intended) || trim($intended) === '') {
            $intended = url()->previous();
        }

        $parsed = parse_url($intended);
        $host = is_array($parsed) && isset($parsed['host']) ? (string) $parsed['host'] : null;
        if ($host !== null && strcasecmp($host, $request->getHost()) !== 0) {
            $intended = route('dashboard');
        }

        $request->session()->put('url.intended', $intended);
    }
}
