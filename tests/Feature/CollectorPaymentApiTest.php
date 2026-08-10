<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('records collector batches with per-item success and error results', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'collector@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $first = Customer::factory()->create();
    $second = Customer::factory()->create();
    $token = $user->createToken('collector', ['api', 'staff:collector'])->plainTextToken;
    $this->withToken($token)
        ->withHeader('X-Idempotency-Key', 'collector-shift-open-001')
        ->postJson('/api/v1/collector/shift/open', ['opening_float' => ['USD' => 0]])
        ->assertCreated();
    $items = [
        ['customer_id' => $first->public_id, 'amount' => 100, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'batch-001'],
        ['customer_id' => $first->public_id, 'amount' => 100, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'batch-001'],
        ['customer_id' => $second->public_id, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'batch-003'],
        ['customer_id' => $second->public_id, 'amount' => 250, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'batch-004'],
    ];

    $response = $this->withToken($token)->withHeader('X-Idempotency-Key', 'collector-request-001')->postJson('/api/v1/collector/payments/batch', ['items' => $items]);

    $response->assertOk()->assertJsonCount(4, 'results');
    expect($response->json('results.0.status'))->toBe('ok')
        ->and($response->json('results.1.status'))->toBe('ok')
        ->and($response->json('results.2.status'))->toBe('error')
        ->and($response->json('results.3.status'))->toBe('ok')
        ->and(Payment::withoutGlobalScopes()->count())->toBe(2);
});
