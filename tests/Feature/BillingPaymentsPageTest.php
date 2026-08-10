<?php

use App\Actions\RecordPayment;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists tenant payments without exposing another tenant record and reverses a posted payment', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'billing@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    $user->forceFill(['last_authenticated_at' => now()])->save();
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $payment = app(RecordPayment::class)->handle($customer, 1200, 'USD', 'cash', 'billing-payments-001', actor: $user);

    app(Tenancy::class)->set($otherTenant);
    $otherCustomer = Customer::factory()->create(['balance_currency' => 'USD']);
    Payment::create(['number' => 'RCT-OTHER', 'customer_id' => $otherCustomer->id, 'status' => PaymentStatus::Posted, 'amount' => 900, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'billing-payments-other', 'received_at' => now()]);

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->get(route('billing.payments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/Payments')
            ->where('payments.total', 1)
            ->where('payments.data.0.number', $payment->number)
            ->where('payments.data.0.customer.public_id', $customer->public_id)
            ->missing('payments.data.0.idempotency_key')
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('billing.payments.reverse', $payment->public_id))
        ->assertRedirect(route('billing.payments'));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Reversed);
});

it('rejects payment reversal without the void capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    $user->forceFill(['last_authenticated_at' => now()])->save();
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('cashier');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $payment = Payment::create(['number' => 'RCT-CASHIER', 'customer_id' => $customer->id, 'status' => PaymentStatus::Posted, 'amount' => 900, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'billing-payments-cashier', 'received_at' => now()]);

    $this->actingAs($user)
        ->post(route('billing.payments.reverse', $payment->public_id))
        ->assertForbidden();
});
