<?php

use App\Http\Middleware\CaptureRequestContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Responses\ProblemDetails;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CaptureRequestContext::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'tenant' => IdentifyTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $extra = $exception instanceof ValidationException ? ['errors' => $exception->errors()] : [];

            return ProblemDetails::fromThrowable(
                $exception,
                $status,
                $request->header('X-Request-ID', (string) Str::uuid()),
                $extra,
            );
        });
    })->create();
