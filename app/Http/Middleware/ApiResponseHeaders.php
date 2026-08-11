<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Server-Time', now('UTC')->toIso8601String());
        $response->headers->set('X-Request-ID', $request->header('X-Request-ID', (string) Str::uuid()));

        return $response;
    }
}
