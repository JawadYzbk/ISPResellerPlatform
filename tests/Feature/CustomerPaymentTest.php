<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders and records a staff customer payment against an invoice', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));

    $this->actingAs($user)
        ->get(route('customers.payments.create', $customer->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Create')
            ->where('customer.public_id', $customer->public_id)
            ->where('invoices.0.outstanding_amount', 3500)
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('customers.payments.store', $customer->public_id), [
            'amount' => 3500,
            'currency' => 'USD',
            'method' => 'cash',
            'invoice_id' => $invoice->public_id,
            'idempotency_key' => '0198d9a4-0e80-72bb-9ef8-44a7bf6c2189',
        ])
        ->assertRedirect(route('customers.show', $customer->public_id));

    app(Tenancy::class)->set($tenant);
    expect($customer->payments()->count())->toBe(1)
        ->and($customer->refresh()->balance_amount)->toBe(0);
});

it('records an overpayment as customer credit', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('customers.payments.store', $customer->public_id), [
            'amount' => 3501,
            'currency' => 'USD',
            'method' => 'cash',
            'invoice_id' => $invoice->public_id,
            'idempotency_key' => '0198d9a4-0e80-72bb-9ef8-44a7bf6c2190',
        ])
        ->assertRedirect(route('customers.show', $customer->public_id));

    app(Tenancy::class)->set($tenant);
    expect($customer->payments()->count())->toBe(1)
        ->and($customer->refresh()->balance_amount)->toBe(-1);
});

it('renders a year of monthly invoice payment status on the customer page', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'grid-owner@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subYear()]);

    $paidInvoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $paidInvoice->forceFill(['issued_at' => CarbonImmutable::create(2025, 1, 15), 'due_at' => CarbonImmutable::create(2025, 1, 20)])->save();
    app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', '0198d9a4-0e80-72bb-9ef8-44a7bf6c2200', $paidInvoice, $user);

    $dueInvoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $dueInvoice->forceFill(['issued_at' => CarbonImmutable::create(2025, 2, 15), 'due_at' => CarbonImmutable::create(2025, 2, 20)])->save();

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->get(route('customers.show', $customer->public_id).'?year=2025')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Show')
            ->where('paymentGrid.year', 2025)
            ->where('paymentGrid.months.0.status', 'paid')
            ->where('paymentGrid.months.0.totals.0.paid_amount', 3500)
            ->where('paymentGrid.months.1.status', 'due')
            ->where('paymentGrid.months.1.totals.0.outstanding_amount', 3500)
        );
});
