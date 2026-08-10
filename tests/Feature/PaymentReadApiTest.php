<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists and reads tenant payments through the billing API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'payment-read-api@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('cashier');
    $customer = Customer::factory()->create(['first_name' => 'Maya']);
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $payment = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'payment-read-001', $invoice, $user);
    $token = $user->createToken('payment-read-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/payments?filter[status]=posted&filter[search]='.$payment->number)
        ->assertOk()
        ->assertJsonPath('data.0.id', $payment->public_id)
        ->assertJsonPath('data.0.customer.id', $customer->public_id)
        ->assertJsonPath('data.0.amount', 3500);

    $this->withToken($token)->getJson('/api/v1/payments/'.$payment->public_id)
        ->assertOk()
        ->assertJsonPath('id', $payment->public_id)
        ->assertJsonPath('invoice.id', $invoice->public_id)
        ->assertJsonMissingPath('idempotency_key');
});

it('does not expose payments to staff without collection access', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'email' => 'payment-reader@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('support_agent');
    $token = $user->createToken('payment-reader-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/payments')->assertForbidden();
});
