<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists and reads tenant invoices through the operator API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing', 'email' => 'invoice-api@example.test', 'password' => Hash::make('password'), 'role' => 'billing_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('billing_manager');
    $customer = Customer::factory()->create(['first_name' => 'Maya']);
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $token = $user->createToken('invoice-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/invoices?filter[status]=issued&filter[search]='.$invoice->number)
        ->assertOk()
        ->assertJsonPath('data.0.id', $invoice->public_id)
        ->assertJsonPath('data.0.customer.id', $customer->public_id)
        ->assertJsonPath('data.0.outstanding_amount', 3500)
        ->assertJsonPath('data.0.lines.0.total_amount', 3500);

    $this->withToken($token)->getJson('/api/v1/invoices/'.$invoice->public_id)
        ->assertOk()
        ->assertJsonPath('id', $invoice->public_id)
        ->assertJsonPath('number', $invoice->number)
        ->assertJsonPath('status', 'issued');
});

it('does not expose invoices to staff without billing access', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'email' => 'invoice-reader@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('support_agent');
    $token = $user->createToken('invoice-reader-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/invoices')->assertForbidden();
});
