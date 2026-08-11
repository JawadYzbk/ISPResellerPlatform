<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function configurePlatformMonitoring(): void
{
    config()->set([
        'monitoring.enabled' => true,
        'monitoring.webhook_url' => 'https://alerts.isp.internal/platform',
        'monitoring.webhook_secret' => 'monitoring-secret',
        'monitoring.timeout' => 5,
    ]);
    Cache::forget('platform:monitor:last-status');
}

it('deduplicates degraded alerts and emits a signed recovery event', function (): void {
    configurePlatformMonitoring();
    Http::fake(['https://alerts.isp.internal/*' => Http::response([], 202)]);
    Cache::put('scheduler_heartbeat', now()->subMinutes(6)->toIso8601String(), now()->addMinutes(5));
    Cache::put('queue_worker_heartbeat', now()->subMinutes(6)->toIso8601String(), now()->addMinutes(5));

    $this->artisan('platform:monitor')
        ->assertExitCode(1)
        ->expectsOutput('Platform health: degraded; alert: sent.');

    $this->artisan('platform:monitor')
        ->assertExitCode(1)
        ->expectsOutput('Platform health: degraded; alert: suppressed.');

    Http::assertSentCount(1);
    Http::assertSent(function ($request): bool {
        $payload = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);

        return $request->hasHeader('X-Platform-Alert-Signature')
            && hash_equals(
                hash_hmac('sha256', $request->body(), 'monitoring-secret'),
                (string) $request->header('X-Platform-Alert-Signature')[0],
            )
            && $payload['event'] === 'degraded';
    });

    Cache::put('scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(5));
    Cache::put('queue_worker_heartbeat', now()->toIso8601String(), now()->addMinutes(5));

    $this->artisan('platform:monitor')
        ->assertSuccessful()
        ->expectsOutput('Platform health: ok; alert: sent.');

    Http::assertSentCount(2);
    Http::assertSent(function ($request): bool {
        $payload = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['event'] === 'recovered';
    });
});

it('records a healthy baseline without sending an alert', function (): void {
    configurePlatformMonitoring();
    Http::fake();
    Cache::put('scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(5));
    Cache::put('queue_worker_heartbeat', now()->toIso8601String(), now()->addMinutes(5));

    $this->artisan('platform:monitor')
        ->assertSuccessful()
        ->expectsOutput('Platform health: ok; alert: baseline.');

    Http::assertNothingSent();
});

it('reports health without alert delivery when monitoring is disabled', function (): void {
    config()->set('monitoring.enabled', false);
    Cache::put('scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(5));
    Cache::put('queue_worker_heartbeat', now()->toIso8601String(), now()->addMinutes(5));

    $this->artisan('platform:monitor')
        ->assertSuccessful()
        ->expectsOutput('Platform health: ok; alert: disabled.');
});
