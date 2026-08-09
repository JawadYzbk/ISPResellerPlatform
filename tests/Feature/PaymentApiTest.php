<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('uses cursor pagination and whitelisted filters for customer API reads', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Staff', 'email' => 'staff@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    Customer::factory()->create(['first_name' => 'Maya']);
    $token = $user->createToken('api-test', ['api'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/customers?filter[search]=Maya')->assertOk()->assertJsonPath('data.0.first_name', 'Maya');
});

it('replays an API payment response without duplicating the payment', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('cashier');
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $token = $user->createToken('api-test', ['api'])->plainTextToken;
    $payload = ['customer_id' => $customer->public_id, 'invoice_id' => $invoice->public_id, 'amount' => 3500, 'currency' => 'USD', 'method' => 'cash'];

    $first = $this->withToken($token)->withHeader('X-Idempotency-Key', 'api-payment-001')->postJson('/api/v1/payments', $payload);
    $second = $this->withToken($token)->withHeader('X-Idempotency-Key', 'api-payment-001')->postJson('/api/v1/payments', $payload);

    $first->assertCreated();
    $second->assertCreated()->assertJsonPath('id', $first->json('id'));
    expect(Payment::withoutGlobalScopes()->count())->toBe(1);
});

it('rejects a reused API idempotency key with a different request', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('cashier');
    $customer = Customer::factory()->create();
    $token = $user->createToken('api-test', ['api'])->plainTextToken;
    $headers = ['X-Idempotency-Key' => 'api-payment-002'];
    $payload = ['customer_id' => $customer->public_id, 'amount' => 100, 'currency' => 'USD', 'method' => 'cash'];

    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/payments', $payload)->assertCreated();
    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/payments', [...$payload, 'amount' => 200])->assertStatus(409);
});
