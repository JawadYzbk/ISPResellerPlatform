<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('keeps customer API resources tenant-scoped', function (): void {
    $north = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $south = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $north->id, 'name' => 'Staff', 'email' => 'staff@example.test', 'password' => Hash::make('password'), 'role' => 'reseller_staff']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($north);
    $user->assignRole('reseller_staff');
    $northCustomer = Customer::factory()->create();
    app(Tenancy::class)->run($south, fn (): Customer => Customer::factory()->create());

    $token = $user->createToken('api-test', ['api'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/customers')->assertOk()->assertJsonPath('data.0.id', $northCustomer->id);
    $this->withToken($token)->getJson('/api/v1/customers/'.$northCustomer->public_id)->assertOk()->assertJsonPath('id', $northCustomer->id);
    $southCustomer = Customer::withoutGlobalScopes()->where('tenant_id', $south->id)->firstOrFail();
    $this->withToken($token)->getJson('/api/v1/customers/'.$southCustomer->public_id)->assertNotFound();
});
