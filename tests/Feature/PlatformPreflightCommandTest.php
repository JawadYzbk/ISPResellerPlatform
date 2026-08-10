<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('passes the baseline preflight when the database and key are ready', function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    $this->artisan('platform:preflight')
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Preflight passed.');
});

it('fails the production preflight for unsafe public configuration', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'local',
        'app.debug' => true,
        'app.url' => 'http://localhost',
        'session.secure' => false,
        'queue.default' => 'sync',
        'cache.default' => 'array',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Preflight failed:');
});

it('passes the production preflight for a production-shaped configuration', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.example.com',
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Preflight passed.');
});

it('rejects a placeholder application key', function (): void {
    config()->set('app.key', 'base64:placeholder');

    $this->artisan('platform:preflight')
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Application key');
});

it('rejects a production tenant with an unassigned capability role', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_owner']);
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.example.com',
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Capability assignments');
});
