<?php

use App\Enums\ServiceStatus;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('queues a tenant-authorized active service re-sync', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'operations@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $customerPublicId = $service->customer->public_id;

    $this->actingAs($user)
        ->post(route('services.resync', $service->public_id))
        ->assertRedirect(route('customers.show', $customerPublicId));

    app(Tenancy::class)->set($tenant);
    $command = NetworkCommand::query()->firstOrFail();

    expect($command->action)->toBe('activate')
        ->and($command->payload['reason'])->toBe('manual_resync')
        ->and($command->desired_state_version)->toBe(2);
});

it('does not enqueue a re-sync for a terminated service', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'operations@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create(['status' => ServiceStatus::Terminated]);

    $this->actingAs($user)
        ->post(route('services.resync', $service->public_id))
        ->assertStatus(422);

    expect(NetworkCommand::count())->toBe(0);
});
