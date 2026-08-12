<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function manualInvoiceUser(Tenant $tenant): User
{
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Billing manager',
        'email' => 'manual-invoice@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    return $user;
}

it('renders the manual invoice workspace with tenant currencies and searchable customers', function (): void {
    config()->set('services.frankfurter.currency_catalog_enabled', false);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    $user = manualInvoiceUser($tenant);
    $customer = Customer::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Haddad']);

    $this->actingAs($user)
        ->get(route('billing.invoices.create', ['customer_id' => $customer->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/Invoices/Create')
            ->where('defaultCurrency', 'USD')
            ->where('selectedCustomer.id', $customer->public_id)
            ->where('currencies.0.code', 'USD'));

    $this->actingAs($user)
        ->getJson(route('billing.invoices.customers', ['search' => 'Nadia']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $customer->public_id)
        ->assertJsonPath('data.0.name', 'Nadia Haddad');
});

it('creates a tenant-scoped manual draft invoice with a price snapshot', function (): void {
    config()->set('services.frankfurter.currency_catalog_enabled', false);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    $user = manualInvoiceUser($tenant);
    $customer = Customer::factory()->create(['first_name' => 'Rami']);

    $this->actingAs($user)
        ->post(route('billing.invoices.store'), [
            'customer_id' => $customer->public_id,
            'description' => 'Installation fee',
            'amount' => 12500,
            'currency' => 'LBP',
            'due_at' => '2026-08-20',
        ])
        ->assertRedirect()
        ->assertSessionHas('success_title', 'Invoice created');

    app(Tenancy::class)->set($tenant);
    $invoice = Invoice::query()->where('customer_id', $customer->id)->latest('id')->firstOrFail();
    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->total_amount)->toBe(12500)
        ->and($invoice->due_at?->toDateString())->toBe('2026-08-20')
        ->and($invoice->lines->first()->description)->toBe('Installation fee')
        ->and($invoice->lines->first()->price_snapshot)->toMatchArray([
            'amount_minor' => 12500,
            'currency' => 'LBP',
            'decimal_digits' => 0,
            'source' => 'operator',
        ]);
});

it('can create and issue a manual invoice through the ledger lifecycle', function (): void {
    config()->set('services.frankfurter.currency_catalog_enabled', false);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = manualInvoiceUser($tenant);
    $customer = Customer::factory()->create();

    $this->actingAs($user)
        ->post(route('billing.invoices.store'), [
            'customer_id' => $customer->public_id,
            'description' => 'Manual service charge',
            'amount' => 3500,
            'currency' => 'USD',
            'issue' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success_title', 'Invoice issued');

    app(Tenancy::class)->set($tenant);
    $invoice = Invoice::query()->where('customer_id', $customer->id)->latest('id')->firstOrFail();
    expect($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->issued_at)->not->toBeNull()
        ->and($invoice->total_amount)->toBe(3500);
});

it('does not search or create invoices for another tenant customer', function (): void {
    config()->set('services.frankfurter.currency_catalog_enabled', false);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = manualInvoiceUser($tenant);
    app(Tenancy::class)->set($otherTenant);
    $otherCustomer = Customer::factory()->create(['first_name' => 'Southline']);
    app(Tenancy::class)->set($tenant);

    $this->actingAs($user)
        ->getJson(route('billing.invoices.customers', ['search' => 'Southline']))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($user)
        ->post(route('billing.invoices.store'), [
            'customer_id' => $otherCustomer->public_id,
            'description' => 'Cross-tenant attempt',
            'amount' => 1000,
            'currency' => 'USD',
        ])
        ->assertNotFound();
});
