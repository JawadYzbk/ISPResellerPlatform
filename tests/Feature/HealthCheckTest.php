<?php

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('reports dependency health without authentication', function (): void {
    $this->artisan('platform:heartbeat')->assertSuccessful();
    Cache::put('queue_worker_heartbeat', now()->toIso8601String(), now()->addMinutes(5));

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', 'ok')
        ->assertJsonPath('checks.migrations', 'ok')
        ->assertJsonPath('checks.migration_pending', 0)
        ->assertJsonPath('checks.cache', 'ok')
        ->assertJsonPath('checks.queue', 'ok')
        ->assertJsonPath('checks.queue_depth', 0)
        ->assertJsonPath('checks.scheduler', 'ok')
        ->assertJsonPath('checks.queue_worker', 'ok');
});

it('degrades when scheduler and queue-worker heartbeats are stale', function (): void {
    Cache::put('scheduler_heartbeat', now()->subMinutes(6)->toIso8601String(), now()->addMinutes(5));
    Cache::put('queue_worker_heartbeat', now()->subMinutes(6)->toIso8601String(), now()->addMinutes(5));

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.scheduler', 'stale')
        ->assertJsonPath('checks.queue_worker', 'stale');
});

it('degrades when database migrations are pending', function (): void {
    $repository = Mockery::mock();
    $repository->shouldReceive('getRan')->once()->andReturn([]);
    $migrator = Mockery::mock(Migrator::class);
    $migrator->shouldReceive('paths')->once()->andReturn([]);
    $migrator->shouldReceive('getMigrationFiles')->once()->andReturn(['2026_08_10_999999_pending_migration' => 'pending']);
    $migrator->shouldReceive('getRepository')->once()->andReturn($repository);
    app()->instance(Migrator::class, $migrator);

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.migrations', 'pending')
        ->assertJsonPath('checks.migration_pending', 1);
});
