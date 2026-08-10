<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $styleSources = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $scriptSources = ["'self'", "'unsafe-inline'"];
        $connectSources = ["'self'"];

        if (in_array((string) config('app.env'), ['local', 'testing'], true)) {
            $styleSources = [...$styleSources, 'http://localhost:5173', 'http://127.0.0.1:5173', 'http://[::1]:5173'];
            $scriptSources = [...$scriptSources, "'unsafe-eval'", 'http://localhost:5173', 'http://127.0.0.1:5173', 'http://[::1]:5173'];
            $connectSources = [...$connectSources, 'ws://localhost:5173', 'ws://127.0.0.1:5173', 'ws://[::1]:5173', 'http://localhost:5173', 'http://127.0.0.1:5173', 'http://[::1]:5173'];
        }

        $reverbOptions = config('broadcasting.connections.reverb.options', []);
        $reverbHost = is_array($reverbOptions) && is_string($reverbOptions['host'] ?? null) ? trim($reverbOptions['host']) : '';
        if ($reverbHost !== '') {
            $reverbScheme = is_array($reverbOptions) && ($reverbOptions['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';
            $reverbPort = is_array($reverbOptions) && is_numeric($reverbOptions['port'] ?? null) ? (int) $reverbOptions['port'] : ($reverbScheme === 'wss' ? 443 : 80);
            $reverbAuthority = str_contains($reverbHost, ':') && ! str_starts_with($reverbHost, '[') ? '['.$reverbHost.']' : $reverbHost;
            $connectSources[] = $reverbScheme.'://'.$reverbAuthority.':'.$reverbPort;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(self), microphone=()');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: https:; style-src ".implode(' ', $styleSources)."; font-src 'self' https://fonts.gstatic.com data:; script-src ".implode(' ', $scriptSources).'; connect-src '.implode(' ', $connectSources));

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
