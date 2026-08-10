<?php

use App\Enums\ServiceStatus;
use App\Models\CurrentSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NetworkCommand;
use App\Models\Plan;
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

    $this->withToken($token)->getJson('/api/v1/services?filter[status]=active')
        ->assertOk()
        ->assertJsonPath('data.0.id', $service->public_id)
        ->assertJsonMissingPath('data.0.password_encrypted');

    $headers = ['X-Idempotency-Key' => 'service-suspend-001'];
    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/suspend', ['reason' => 'manual']);
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/suspend', ['reason' => 'manual']);

    $first->assertStatus(202)->assertJsonPath('status', 'suspended');
    $second->assertStatus(202)->assertJsonPath('command_id', $first->json('command_id'));
    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->status)->toBe(ServiceStatus::Suspended)
        ->and(NetworkCommand::count())->toBe(1);
});

it('pauses and resumes a service while preserving its billing expiry', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'service-pause-api@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $expiresAt = now()->addDays(14)->startOfSecond();
    $resumeAt = now()->addDays(3)->startOfSecond();
    $service = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => $expiresAt]);
    $token = $user->createToken('service-pause-api', ['api', 'staff:operator'])->plainTextToken;

    $first = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => 'service-pause-001'])
        ->postJson('/api/v1/services/'.$service->public_id.'/pause', ['reason' => 'customer_requested', 'resume_at' => $resumeAt->toDateTimeString()]);
    $second = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => 'service-pause-001'])
        ->postJson('/api/v1/services/'.$service->public_id.'/pause', ['reason' => 'customer_requested', 'resume_at' => $resumeAt->toDateTimeString()]);

    $first->assertStatus(202)
        ->assertJsonPath('status', 'paused')
        ->assertJsonPath('paused_until', $resumeAt->toIso8601String());
    $second->assertStatus(202)->assertJsonPath('command_id', $first->json('command_id'));
    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->status)->toBe(ServiceStatus::Paused)
        ->and($service->expires_at?->equalTo($expiresAt))->toBeTrue()
        ->and(NetworkCommand::query()->where('service_id', $service->id)->where('action', 'pause')->count())->toBe(1);

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => 'service-resume-paused-001'])
        ->postJson('/api/v1/services/'.$service->public_id.'/resume')
        ->assertStatus(202)
        ->assertJsonPath('status', 'active');
});

it('idempotently terminates a service through the operator API', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'service-termination-api@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $token = $user->createToken('service-termination-api', ['api', 'staff:operator'])->plainTextToken;
    $headers = ['X-Idempotency-Key' => 'service-terminate-001'];

    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/terminate', ['reason' => 'Customer requested cancellation']);
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/terminate', ['reason' => 'Customer requested cancellation']);

    $first->assertStatus(202)->assertJsonPath('status', 'terminated')->assertJsonPath('command_id', fn (mixed $id): bool => is_string($id));
    $second->assertStatus(202)->assertJsonPath('command_id', $first->json('command_id'));
    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->status)->toBe(ServiceStatus::Terminated)
        ->and(NetworkCommand::query()->where('service_id', $service->id)->where('action', 'disconnect')->count())->toBe(1);
});

it('queues an idempotent current-session disconnect with the live session id', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network', 'email' => 'service-disconnect-api@example.test', 'password' => Hash::make('password'), 'role' => 'network_administrator']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('network_administrator');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    CurrentSession::create(['service_id' => $service->id, 'username' => $service->username, 'acct_session_id' => 'live-session-001', 'nasname' => 'router-01', 'last_seen_at' => now()]);
    $token = $user->createToken('service-disconnect-api', ['api', 'staff:operator'])->plainTextToken;
    $headers = ['X-Idempotency-Key' => 'service-disconnect-001'];

    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/disconnect-session');
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/disconnect-session');

    $first->assertStatus(202)
        ->assertJsonPath('status', 'disconnect_queued')
        ->assertJsonPath('session_id', 'live-session-001');
    $second->assertStatus(202)->assertJsonPath('command_id', $first->json('command_id'));
    app(Tenancy::class)->set($tenant);
    expect(NetworkCommand::query()->where('service_id', $service->id)->where('action', 'disconnect')->count())->toBe(1)
        ->and(NetworkCommand::query()->where('service_id', $service->id)->firstOrFail()->payload['session_id'])->toBe('live-session-001');
});

it('previews and idempotently applies a service plan change through the operator API', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'service-plan-api@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $oldPlan = Plan::factory()->create(['amount_minor' => 100, 'currency' => 'USD']);
    $newPlan = Plan::factory()->create(['amount_minor' => 200, 'currency' => 'USD']);
    $oldPlan->prices()->create(['currency' => 'USD', 'amount_minor' => 100, 'effective_from' => now()->subDay()]);
    $newPlan->prices()->create(['currency' => 'USD', 'amount_minor' => 200, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->for($customer)->for($oldPlan)->create([
        'status' => ServiceStatus::Active,
        'activated_at' => now()->subDays(10),
        'expires_at' => now()->addDays(20),
    ]);
    $token = $user->createToken('service-plan-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/services/'.$service->public_id.'/plan-change-previews', ['plan_uuid' => $newPlan->public_id, 'effective' => 'immediate'])
        ->assertOk()
        ->assertJsonPath('from_plan_id', $oldPlan->public_id)
        ->assertJsonPath('to_plan_id', $newPlan->public_id)
        ->assertJsonPath('old_credit_amount', 67)
        ->assertJsonPath('new_charge_amount', 133)
        ->assertJsonPath('net_amount', 66);

    $headers = ['X-Idempotency-Key' => 'service-plan-change-001'];
    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/change-plan', ['plan_uuid' => $newPlan->public_id, 'effective' => 'immediate']);
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/change-plan', ['plan_uuid' => $newPlan->public_id, 'effective' => 'immediate']);

    $first->assertStatus(202)->assertJsonPath('plan_id', $newPlan->public_id);
    $second->assertStatus(202)->assertJsonPath('command_id', $first->json('command_id'));
    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->plan_id)->toBe($newPlan->id)
        ->and(NetworkCommand::query()->where('service_id', $service->id)->where('action', 'change_plan')->count())->toBe(1);
});

it('previews and idempotently issues a multi-period renewal invoice through the operator API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'service-renewal-api@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $plan = Plan::factory()->create(['amount_minor' => 100, 'duration_days' => 30, 'currency' => 'USD']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 100, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->for($customer)->for($plan)->create([
        'status' => ServiceStatus::Active,
        'expires_at' => now()->addDays(5),
    ]);
    $before = $service->expires_at;
    $token = $user->createToken('service-renewal-api', ['api', 'staff:operator'])->plainTextToken;

    $preview = $this->withToken($token)
        ->postJson('/api/v1/services/'.$service->public_id.'/renewal-previews', ['periods' => 2])
        ->assertOk()
        ->assertJsonPath('service_id', $service->public_id)
        ->assertJsonPath('plan_id', $plan->public_id)
        ->assertJsonPath('periods', 2)
        ->assertJsonPath('amount', 200)
        ->json('preview_id');

    $headers = ['X-Idempotency-Key' => 'service-renewal-001'];
    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/renewals', ['preview_id' => $preview, 'periods' => 2]);
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/services/'.$service->public_id.'/renewals', ['preview_id' => $preview, 'periods' => 2]);

    $first->assertStatus(202)
        ->assertJsonPath('service_id', $service->public_id)
        ->assertJsonPath('status', 'invoice_issued')
        ->assertJsonPath('amount', 200)
        ->assertJsonPath('periods', 2);
    $second->assertStatus(202)->assertJsonPath('invoice_id', $first->json('invoice_id'));
    app(Tenancy::class)->set($tenant);
    $invoice = Invoice::query()->where('public_id', $first->json('invoice_id'))->firstOrFail();

    expect(Invoice::count())->toBe(1)
        ->and($invoice->metadata['renewal_periods'])->toBe(2)
        ->and($invoice->lines()->firstOrFail()->quantity)->toBe(2)
        ->and($service->refresh()->expires_at->equalTo($before))->toBeTrue();
});
