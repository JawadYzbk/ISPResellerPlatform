<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('exports only the selected tenant customers as CSV', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'customer-export@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $selected = Customer::factory()->create(['first_name' => 'Selected', 'last_name' => 'Customer', 'code' => 'CUS-SELECTED']);
    Customer::factory()->create(['first_name' => 'Other', 'last_name' => 'Customer', 'code' => 'CUS-OTHER']);

    $response = $this->actingAs($user)->get(route('customers.export', ['selected' => [$selected->public_id]]));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    expect($csv)->toContain('customer_id,customer_code')
        ->and($csv)->toContain('CUS-SELECTED')
        ->and($csv)->not->toContain('CUS-OTHER');
});

it('does not export a selected customer from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'customer-export-isolation@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $local = Customer::factory()->create(['code' => 'CUS-LOCAL']);
    app(Tenancy::class)->set($otherTenant);
    $foreign = Customer::factory()->create(['code' => 'CUS-FOREIGN']);
    app(Tenancy::class)->set($tenant);

    $response = $this->actingAs($user)->get(route('customers.export', ['selected' => [$local->public_id, $foreign->public_id]]));

    $csv = $response->streamedContent();
    expect($csv)->toContain('CUS-LOCAL')->and($csv)->not->toContain('CUS-FOREIGN');
});
