<?php

use App\Http\Middleware\AuthenticatePortalSession;
use App\Http\Middleware\CaptureRequestContext;
use App\Http\Middleware\EnsureApiIdempotency;
use App\Http\Middleware\EnsureRecentAuthentication;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyPortalTenant;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Responses\ProblemDetails;
use Illuminate\Console\Scheduling\Schedule;
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
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('services:suspend-overdue')->hourlyAt(5);
        $schedule->command('metrics:prune')->dailyAt('02:10');
        $schedule->command('notifications:expiry-reminders')->hourlyAt(10);
        $schedule->command('radius:mark-stale-sessions')->everyFifteenMinutes();
        $schedule->command('tickets:auto-close-resolved')->hourlyAt(20);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CaptureRequestContext::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            '2fa' => EnsureTwoFactorVerified::class,
            'recent-auth' => EnsureRecentAuthentication::class,
            'idempotency' => EnsureApiIdempotency::class,
            'portal.tenant' => IdentifyPortalTenant::class,
            'portal.auth' => AuthenticatePortalSession::class,
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
