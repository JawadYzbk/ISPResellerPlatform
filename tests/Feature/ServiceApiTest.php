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

it('lists services and idempotently queues a suspend command', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'service-api@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $token = $user->createToken('service-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/services?filter[status]=active')->assertOk()->assertJsonPath('data.0.id', $service->id);

    $headers = ['X-Idempotency-Key' => 'service-suspend-001'];
    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/suspend', ['reason' => 'manual']);
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/suspend', ['reason' => 'manual']);

    $first->assertStatus(202)->assertJsonPath('status', 'suspended');
    $second->assertStatus(202)->assertJsonPath('command_id', $first->json('command_id'));
    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->status)->toBe(ServiceStatus::Suspended)
        ->and(NetworkCommand::count())->toBe(1);
});
