<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Models\Customer;
use App\Models\ExchangeRate;
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
    $token = $user->createToken('api-test', ['api', 'staff:operator'])->plainTextToken;

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
    $token = $user->createToken('api-test', ['api', 'staff:operator'])->plainTextToken;
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
    $token = $user->createToken('api-test', ['api', 'staff:operator'])->plainTextToken;
    $headers = ['X-Idempotency-Key' => 'api-payment-002'];
    $payload = ['customer_id' => $customer->public_id, 'amount' => 100, 'currency' => 'USD', 'method' => 'cash'];

    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/payments', $payload)->assertCreated();
    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/payments', [...$payload, 'amount' => 200])->assertStatus(409);
});

it('records a multi-currency API payment with the FX snapshot and audit fields', function (): void {
    $tenant = Tenant::create(['name' => 'Lebanon', 'slug' => 'lebanon', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    ExchangeRate::create(['base_currency' => 'USD', 'quote_currency' => 'LBP', 'rate_numerator' => 90_000, 'rate_denominator' => 1, 'effective_from' => now()->subDay(), 'source' => 'treasury']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'fx-api@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('cashier');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $plan = Plan::factory()->create(['amount_minor' => 100, 'currency' => 'USD']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 100, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $token = $user->createToken('fx-api', ['api', 'staff:operator'])->plainTextToken;

    $response = $this->withToken($token)->withHeader('X-Idempotency-Key', 'fx-api-001')->postJson('/api/v1/payments', [
        'customer_id' => $customer->public_id,
        'invoice_id' => $invoice->public_id,
        'amount' => 10_000_000,
        'currency' => 'LBP',
        'method' => 'cash',
        'fx_override' => true,
        'fx_rate_numerator' => 1,
        'fx_rate_denominator' => 100_000,
        'fx_override_reason' => 'Approved counter rate',
        'reference' => 'counter-001',
    ]);

    $response->assertCreated()
        ->assertJsonPath('amount', 10_000_000)
        ->assertJsonPath('currency', 'LBP')
        ->assertJsonPath('ledger_amount', 100)
        ->assertJsonPath('ledger_currency', 'USD')
        ->assertJsonPath('base_amount', 100)
        ->assertJsonPath('fx_rate_overridden', true)
        ->assertJsonPath('reference', 'counter-001');
});
