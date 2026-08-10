<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('runs a customer CSV import and rollback through the API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner-import@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $token = $user->createToken('importer', ['api', 'staff:operator'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/imports/customers', ['filename' => 'customers.csv', 'dry_run' => false, 'csv' => "first_name,phone\nAda,+96170123456"]);
    $response->assertCreated()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('successful_rows', 1)
        ->assertJsonMissingPath('report.0.customer_id');
    app(Tenancy::class)->set($tenant);
    expect(Customer::count())->toBe(1);

    $this->withToken($token)->postJson('/api/v1/imports/'.$response->json('id').'/rollback')
        ->assertOk()
        ->assertJsonPath('status', 'rolled_back')
        ->assertJsonPath('deleted_customers', 1);
    app(Tenancy::class)->set($tenant);
    expect(Customer::count())->toBe(0);
});
