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

it('lists and polls sanitized tenant network command history', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network', 'email' => 'network-command-api@example.test', 'password' => Hash::make('password'), 'role' => 'network_administrator']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('network_administrator');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $command = NetworkCommand::create([
        'service_id' => $service->id,
        'action' => 'disconnect',
        'status' => 'completed',
        'desired_state_version' => 2,
        'attempts' => 1,
        'payload' => ['session_id' => 'session-001', 'password' => 'never-return-this'],
        'result' => ['coa_status' => 'ack'],
        'completed_at' => now(),
    ]);
    $token = $user->createToken('network-command-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/services/'.$service->public_id.'/network-commands')
        ->assertOk()
        ->assertJsonPath('data.0.id', $command->public_id)
        ->assertJsonPath('data.0.service.id', $service->public_id)
        ->assertJsonMissingPath('data.0.payload')
        ->assertJsonMissingPath('data.0.service.password_encrypted');

    $this->withToken($token)->getJson('/api/v1/network-commands/'.$command->public_id)
        ->assertOk()
        ->assertJsonPath('id', $command->public_id)
        ->assertJsonPath('result.coa_status', 'ack')
        ->assertJsonMissingPath('payload');
});

it('retries a failed command through the provisioning command contract', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network', 'email' => 'provisioning-retry-api@example.test', 'password' => Hash::make('password'), 'role' => 'network_administrator']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('network_administrator');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $command = NetworkCommand::create([
        'service_id' => $service->id,
        'action' => 'activate',
        'status' => 'abandoned',
        'desired_state_version' => 1,
        'last_error' => 'router offline',
    ]);
    $token = $user->createToken('provisioning-retry-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => 'provisioning-retry-001'])
        ->postJson('/api/v1/provisioning-commands/'.$command->public_id.'/retries')
        ->assertAccepted()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('desired_state_version', 2);

    app(Tenancy::class)->set($tenant);
    expect(NetworkCommand::query()->count())->toBe(2)
        ->and(NetworkCommand::query()->latest('id')->firstOrFail()->payload['retry_of'])->toBe($command->id);
});
