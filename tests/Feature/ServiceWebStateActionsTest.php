<?php

use App\Enums\ServiceStatus;
use App\Models\CurrentSession;
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

it('runs web activation and suspension through the state machine and outbox', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'operations@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create(['status' => ServiceStatus::Pending]);
    $customerPublicId = $service->customer->public_id;

    $this->actingAs($user)->post(route('services.activate', $service->public_id))->assertRedirect(route('customers.show', $customerPublicId));
    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->status)->toBe(ServiceStatus::Active);

    $this->actingAs($user)->post(route('services.suspend', $service->public_id), ['reason' => 'manual_operator'])->assertRedirect(route('customers.show', $customerPublicId));
    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->status)->toBe(ServiceStatus::Suspended)
        ->and(NetworkCommand::query()->where('action', 'suspend')->count())->toBe(1);
});

it('requires force-resume permission for a manually suspended service', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'operations@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('operations_manager');
    $service = Service::factory()->create(['status' => ServiceStatus::Suspended, 'suspension_reason' => 'manual_operator']);

    $this->actingAs($user)->post(route('services.resume', $service->public_id))->assertForbidden();
});

it('terminates a service, returns assigned equipment, and queues a disconnect', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'termination@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $customerPublicId = $service->customer->public_id;

    $this->actingAs($user)->post(route('services.terminate', $service->public_id), ['reason' => 'Customer requested cancellation'])
        ->assertRedirect(route('customers.show', $customerPublicId));

    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->status)->toBe(ServiceStatus::Terminated)
        ->and(NetworkCommand::query()->where('service_id', $service->id)->where('action', 'disconnect')->count())->toBe(1);
});

it('queues a web disconnect for the latest active session', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network', 'email' => 'service-disconnect@example.test', 'password' => Hash::make('password'), 'role' => 'network_administrator']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('network_administrator');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $customerPublicId = $service->customer->public_id;
    CurrentSession::create(['service_id' => $service->id, 'username' => $service->username, 'acct_session_id' => 'web-session-001', 'nasname' => 'router-01', 'last_seen_at' => now()]);

    $this->actingAs($user)->post(route('services.disconnect-session', $service->public_id))
        ->assertRedirect(route('customers.show', $customerPublicId));

    app(Tenancy::class)->set($tenant);
    expect(NetworkCommand::query()->where('service_id', $service->id)->where('action', 'disconnect')->firstOrFail()->payload)
        ->toMatchArray(['reason' => 'operator_disconnect', 'session_id' => 'web-session-001']);
});
