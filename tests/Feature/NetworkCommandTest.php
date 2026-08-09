<?php

use App\Actions\EnqueueNetworkCommand;
use App\Domain\Network\DriverManager;
use App\Domain\Network\DriverResult;
use App\Domain\Network\FakeDriver;
use App\Jobs\ExecuteNetworkCommand;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches network work only after the command transaction commits', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create();

    $command = app(EnqueueNetworkCommand::class)->handle($service, 'activate');

    expect($command->desired_state_version)->toBe(2)
        ->and(NetworkCommand::count())->toBe(1);
    Queue::assertPushed(ExecuteNetworkCommand::class, fn (ExecuteNetworkCommand $job): bool => $job->commandId === $command->id);
});

it('refuses stale commands and records fake driver success', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create();
    $command = app(EnqueueNetworkCommand::class)->handle($service, 'activate');
    $service->increment('desired_state_version');
    $fake = new FakeDriver(['activate' => DriverResult::success('activated')]);
    app()->instance(FakeDriver::class, $fake);
    $job = new ExecuteNetworkCommand($command->id, $tenant->id);
    $job->handle(app(DriverManager::class));

    expect($command->refresh()->status)->toBe('stale');
});
