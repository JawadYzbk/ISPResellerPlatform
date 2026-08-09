<?php

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function tenantForTest(string $slug): Tenant
{
    return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'base_currency' => 'USD', 'collection_currency' => 'USD']);
}

function customerForTenant(Tenant $tenant, string $code): Customer
{
    return app(Tenancy::class)->run($tenant, fn (): Customer => Customer::create([
        'code' => $code,
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'phone' => '+961 70 123 '.substr($code, -3),
        'status' => CustomerStatus::Active,
        'balance_currency' => 'USD',
    ]));
}

it('cannot read another tenant customer through the model scope', function (): void {
    $north = tenantForTest('north');
    $south = tenantForTest('south');
    $customer = customerForTenant($north, '10001');

    app(Tenancy::class)->set($south);

    expect(Customer::find($customer->id))->toBeNull();
});

it('cannot access another tenant customer through route binding', function (): void {
    $north = tenantForTest('north');
    $south = tenantForTest('south');
    $customer = customerForTenant($north, '10002');
    $user = User::create(['tenant_id' => $south->id, 'name' => 'South Operator', 'email' => 'south@example.test', 'password' => Hash::make('password'), 'role' => 'operator']);

    $this->actingAs($user)->get(route('customers.show', $customer->public_id))->assertNotFound();
});
