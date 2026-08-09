<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ProblemDetails extends JsonResponse
{
    /** @param array<string, mixed> $extra */
    public static function fromThrowable(\Throwable $exception, int $status, string $requestId, array $extra = []): self
    {
        return new self([
            'type' => 'about:blank',
            'title' => self::titleForStatus($status),
            'status' => $status,
            'detail' => $status >= 500 ? 'An unexpected error occurred.' : $exception->getMessage(),
            'instance' => request()->getPathInfo(),
            'request_id' => $requestId,
            ...$extra,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }

    private static function titleForStatus(int $status): string
    {
        return match (true) {
            $status >= 500 => 'Internal Server Error',
            $status === 404 => 'Not Found',
            $status === 403 => 'Forbidden',
            $status === 422 => 'Validation Failed',
            default => 'Request Failed',
        };
    }
}
