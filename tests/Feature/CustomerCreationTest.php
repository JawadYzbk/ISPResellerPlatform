<?php

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('generates tenant-local customer codes and normalizes the phone', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Reseller Staff', 'email' => 'owner@example.test', 'password' => Hash::make('password'), 'role' => 'reseller_staff']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('reseller_staff');

    $this->actingAs($user)->post(route('customers.store'), [
        'first_name' => 'Rami', 'last_name' => 'Saad', 'phone' => '+961 70 123 456',
    ])->assertRedirect();

    app(Tenancy::class)->set($tenant);

    expect(Customer::firstOrFail()->code)->toBe('CUS-00001')
        ->and(Customer::firstOrFail()->phone_normalized)->toBe('96170123456');
});

it('rejects a duplicate normalized phone inside a tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    Customer::factory()->create(['phone' => '+961 70 123 456']);

    expect(fn (): Customer => Customer::factory()->create(['phone' => '70 123 456']))->toThrow(UniqueConstraintViolationException::class);
});

it('registers a pending service and installation work order atomically', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations Manager', 'email' => 'ops@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('operations_manager');
    $plan = Plan::factory()->create(['status' => 'active']);

    $this->actingAs($user)->post(route('customers.store'), [
        'first_name' => 'Rami',
        'phone' => '+961 70 123 456',
        'create_service' => true,
        'plan_id' => $plan->id,
        'username' => 'rami.home',
        'password' => 'correct-horse-battery',
        'provisioning_mode' => 'manual',
    ])->assertRedirect();

    app(Tenancy::class)->set($tenant);

    $customer = Customer::firstOrFail();
    $service = Service::firstOrFail();
    $workOrder = WorkOrder::firstOrFail();

    expect($service->customer_id)->toBe($customer->id)
        ->and($service->status->value)->toBe('pending')
        ->and($workOrder->number)->toBe('WO-00001')
        ->and($workOrder->type)->toBe('installation')
        ->and($workOrder->status->value)->toBe('pending')
        ->and($workOrder->events()->count())->toBe(1);
});
