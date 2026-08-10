<?php

use App\Actions\CreateInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders invoice balances and issues a draft invoice from the staff queue', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'billing@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $draft = app(CreateInvoice::class)->handle($customer, $plan);

    $this->actingAs($user)
        ->get(route('billing.invoices', ['status' => 'draft']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/Invoices')
            ->where('invoices.data.0.number', $draft->number)
            ->where('invoices.data.0.outstanding_amount', 3500)
            ->where('filters.status', 'draft')
            ->where('canIssue', true)
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('billing.invoices.issue', $draft->public_id))
        ->assertRedirect(route('billing.invoices'))
        ->assertSessionHas('success', "Invoice {$draft->number} issued.");

    app(Tenancy::class)->set($tenant);
    expect($draft->refresh()->status->value)->toBe('issued');
});

it('does not expose invoices from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'billing@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $customer = Customer::factory()->create();
    $invoice = Invoice::create(['number' => 'INV-SOUTH-001', 'customer_id' => $customer->id, 'status' => 'issued', 'currency' => 'USD', 'total_amount' => 1000]);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)->get(route('billing.invoices'))->assertOk()->assertInertia(fn ($page) => $page->where('invoices.total', 0));
    $this->actingAs($user)->post(route('billing.invoices.issue', $invoice->public_id))->assertNotFound();
});
