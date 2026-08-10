<?php

use App\Enums\CashShiftStatus;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('opens, reconciles, and lists a collector cash shift through the API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'collector-shift-api@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $customer = Customer::factory()->create();
    $token = $user->createToken('collector-shift-api', ['api', 'staff:collector'])->plainTextToken;

    $this->withToken($token)
        ->withHeader('X-Idempotency-Key', 'collector-shift-api-open-001')
        ->postJson('/api/v1/collector/shift/open', ['opening_float' => ['USD' => 500]])
        ->assertCreated()
        ->assertJsonPath('data.opening_float.USD', 500)
        ->assertJsonPath('data.system_totals.USD', 500);

    $this->withToken($token)
        ->withHeader('X-Idempotency-Key', 'collector-shift-api-payment-001')
        ->postJson('/api/v1/payments', [
            'customer_id' => $customer->public_id,
            'amount' => 250,
            'currency' => 'USD',
            'method' => 'cash',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->getJson('/api/v1/collector/shift')
        ->assertOk()
        ->assertJsonPath('data.system_totals.USD', 750)
        ->assertJsonPath('data.payment_count', 1);

    $this->withToken($token)
        ->getJson('/api/v1/collector/payments?date='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.0.amount', 250)
        ->assertJsonPath('data.0.customer.id', $customer->public_id);

    $this->withToken($token)
        ->getJson('/api/v1/collector/summary?date='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.payment_count', 1)
        ->assertJsonPath('data.totals.USD', 250);

    $this->withToken($token)
        ->withHeader('X-Idempotency-Key', 'collector-shift-api-close-001')
        ->postJson('/api/v1/collector/shift/close', ['declared_totals' => ['USD' => 750]])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.variance', false)
        ->assertJsonPath('current_shift', null);

    app(Tenancy::class)->set($tenant);
    expect(CashShift::query()->where('user_id', $user->id)->firstOrFail()->status)->toBe(CashShiftStatus::Closed)
        ->and(Payment::query()->count())->toBe(1);
});

it('rejects a collector payment without an open shift', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'collector-shift-required@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $customer = Customer::factory()->create();
    $token = $user->createToken('collector-shift-required', ['api', 'staff:collector'])->plainTextToken;

    $response = $this->withToken($token)
        ->withHeader('X-Idempotency-Key', 'collector-shift-required-001')
        ->postJson('/api/v1/payments', ['customer_id' => $customer->public_id, 'amount' => 100, 'currency' => 'USD', 'method' => 'cash']);
    $response->assertStatus(423)
        ->assertJsonPath('detail', 'An open cash shift is required before recording collector payments.');
});
