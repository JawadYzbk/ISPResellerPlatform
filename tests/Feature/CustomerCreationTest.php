<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
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
