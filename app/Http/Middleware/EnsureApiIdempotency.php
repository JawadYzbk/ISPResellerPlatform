<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApiIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $key = trim($request->header('X-Idempotency-Key', ''));
        abort_if($key === '' || mb_strlen($key) > 120, 400, 'X-Idempotency-Key is required for API writes.');
        $requestHash = hash('sha256', $request->method().'|'.$request->path().'|'.serialize($request->all()));
        $existing = IdempotencyKey::query()->where('key', $key)->first();

        if ($existing instanceof IdempotencyKey) {
            abort_if($existing->request_hash !== $requestHash, 409, 'The idempotency key was already used for a different request.');

            return response($existing->response_body, $existing->response_status, $existing->response_headers ?? [])
                ->header('Content-Type', 'application/json');
        }

        $response = $next($request);
        $record = [
            'key' => $key,
            'request_hash' => $requestHash,
            'response_status' => $response->getStatusCode(),
            'response_headers' => ['Content-Type' => $response->headers->get('Content-Type', 'application/json')],
            'response_body' => (string) $response->getContent(),
        ];

        try {
            IdempotencyKey::create($record);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request owns the key; its committed response is authoritative.
        }

        return $response;
    }
}
