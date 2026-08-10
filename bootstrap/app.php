<?php

use App\Http\Middleware\AuthenticatePortalSession;
use App\Http\Middleware\CaptureRequestContext;
use App\Http\Middleware\EnsureApiIdempotency;
use App\Http\Middleware\EnsureRecentAuthentication;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyPortalTenant;
use App\Http\Middleware\IdentifyPortalTenantFromRequest;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Responses\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth', 'tenant', '2fa']])
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('platform:heartbeat')->everyMinute();
        $schedule->command('ledger:check-invariants')->dailyAt('03:00');
        $schedule->command('routers:reconcile-subscribers')->everyFifteenMinutes();
        $schedule->command('services:suspend-overdue')->hourlyAt(5);
        $schedule->command('metrics:prune')->dailyAt('02:10');
        $schedule->command('notifications:expiry-reminders')->hourlyAt(10);
        $schedule->command('radius:mark-stale-sessions')->everyFifteenMinutes();
        $schedule->command('tickets:auto-close-resolved')->hourlyAt(20);
        $schedule->command('billing:generate-invoices')->dailyAt('01:20');
        $schedule->command('fx:sync-frankfurter')->dailyAt('01:35');
        $schedule->command('radius:enforce-quotas')->dailyAt('01:40');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CaptureRequestContext::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);

        $middleware->priority([
            Authenticate::class,
            IdentifyTenant::class,
            EnsureTwoFactorVerified::class,
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            '2fa' => EnsureTwoFactorVerified::class,
            'recent-auth' => EnsureRecentAuthentication::class,
            'idempotency' => EnsureApiIdempotency::class,
            'portal.tenant' => IdentifyPortalTenant::class,
            'portal.tenant.request' => IdentifyPortalTenantFromRequest::class,
            'portal.auth' => AuthenticatePortalSession::class,
            'abilities' => CheckAbilities::class,
            'any-abilities' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                if ($exception instanceof AuthenticationException || $exception instanceof ValidationException) {
                    return null;
                }

                $status = match (true) {
                    $exception instanceof AuthorizationException => 403,
                    $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                    default => 500,
                };

                $pages = [
                    403 => [
                        'title' => 'This action is unauthorized.',
                        'message' => 'Your account is signed in, but it does not have the capability required for this area.',
                    ],
                    404 => [
                        'title' => 'We could not find that page.',
                        'message' => 'The link may be stale, or the record may no longer be available in this tenant.',
                    ],
                    419 => [
                        'title' => 'This page has expired.',
                        'message' => 'Refresh the page and submit the action again.',
                    ],
                    429 => [
                        'title' => 'Too many requests.',
                        'message' => 'Please wait a moment and try again.',
                    ],
                    500 => [
                        'title' => 'Something went wrong.',
                        'message' => 'The request could not be completed. The incident has been recorded for investigation.',
                    ],
                    503 => [
                        'title' => 'The platform is temporarily unavailable.',
                        'message' => 'The service is restarting or undergoing maintenance. Try again shortly.',
                    ],
                ];

                if (isset($pages[$status])) {
                    return Inertia::render('Errors/Http', [
                        'status' => $status,
                        ...$pages[$status],
                    ])->toResponse($request)->setStatusCode($status);
                }

                return null;
            }

            $status = match (true) {
                $exception instanceof ValidationException => 422,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $extra = $exception instanceof ValidationException ? ['errors' => $exception->errors()] : [];

            return ProblemDetails::fromThrowable(
                $exception,
                $status,
                $request->header('X-Request-ID', (string) Str::uuid()),
                $extra,
            );
        });
    })->create();
