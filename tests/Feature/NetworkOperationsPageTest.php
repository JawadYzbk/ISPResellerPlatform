<?php

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

it('renders tenant-safe network commands and retries a failed command', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network admin', 'email' => 'network@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create();
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'status' => 'failed', 'attempts' => 3, 'desired_state_version' => 1, 'last_error' => 'router unreachable']);

    $this->actingAs($user)
        ->get(route('operations.network-commands', ['status' => 'failed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/NetworkCommands')
            ->where('commands.data.0.public_id', $command->public_id)
            ->where('commands.data.0.service.username', $service->username)
            ->where('filters.status', 'failed')
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('operations.network-commands.retry', $command->public_id))
        ->assertRedirect(route('operations.network-commands'));

    app(Tenancy::class)->set($tenant);
    expect(NetworkCommand::query()->count())->toBe(2)
        ->and(NetworkCommand::query()->latest('id')->firstOrFail()->payload['retry_of'])->toBe($command->id);
});

it('does not expose commands from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network admin', 'email' => 'network@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $service = Service::factory()->create();
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'status' => 'failed', 'desired_state_version' => 1]);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)->get(route('operations.network-commands'))->assertOk()->assertInertia(fn ($page) => $page->where('commands.total', 0));
    $this->actingAs($user)->post(route('operations.network-commands.retry', $command->public_id))->assertNotFound();
});
