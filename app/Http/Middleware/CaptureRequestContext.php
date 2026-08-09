<?php

namespace App\Http\Middleware;

use App\Support\RequestContext;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class CaptureRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(RequestContext::class);
        $context->begin($request->header('X-Request-ID'));
        $context->add([
            'tenant_id' => app(Tenancy::class)->id(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);
        Log::withContext($context->values());

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $context->requestId());

            return $response;
        } finally {
            $context->clear();
        }
    }
}
