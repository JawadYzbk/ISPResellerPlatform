<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('returns authoritative server time and a request id on versioned API responses', function (): void {
    $this->artisan('platform:heartbeat')->assertSuccessful();
    Cache::put('queue_worker_heartbeat', now()->toIso8601String(), now()->addMinutes(5));

    $response = $this->withHeader('X-Request-ID', 'client-request-001')->getJson('/api/v1/health');

    $response->assertOk()
        ->assertHeader('X-Request-ID', 'client-request-001')
        ->assertHeader('X-Server-Time');

    expect($response->headers->get('X-Server-Time'))->toBeString()->not->toBe('');
});
