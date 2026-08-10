<?php

use App\Actions\EnqueueNetworkCommand;
use App\Actions\RetryNetworkCommand;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('retries a failed command as a new desired-state version', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create();
    $command = app(EnqueueNetworkCommand::class)->handle($service, 'activate');
    $command->forceFill(['status' => 'abandoned', 'last_error' => 'router offline'])->save();

    $retry = app(RetryNetworkCommand::class)->handle($command->refresh());

    expect($retry->id)->not->toBe($command->id)
        ->and($retry->desired_state_version)->toBeGreaterThan($command->desired_state_version)
        ->and($command->refresh()->status)->toBe('abandoned')
        ->and(NetworkCommand::count())->toBe(2);
});
