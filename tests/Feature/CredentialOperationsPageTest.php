<?php

use App\Actions\ImportCredentials;
use App\Models\CredentialBatch;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\UpstreamCredential;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders supplier credential inventory without exposing secrets', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'credentials@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $supplier = Supplier::create(['name' => 'Upstream', 'code' => 'UP-01']);
    $batch = CredentialBatch::create(['supplier_id' => $supplier->id, 'reference' => 'BATCH-01', 'imported_at' => now()]);
    app(ImportCredentials::class)->handle($batch, [['identifier' => 'cust-001', 'secret' => 'must-not-render']]);
    $credential = UpstreamCredential::firstOrFail();

    $this->actingAs($user)
        ->get(route('operations.credentials', ['status' => 'available']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/Credentials')
            ->where('credentials.data.0.identifier', 'cust-001')
            ->where('credentials.data.0.supplier.code', 'UP-01')
            ->where('filters.status', 'available')
            ->missing('credentials.data.0.secret')
        );

    expect($credential->toArray())->not->toHaveKey('secret');
});

it('does not expose credentials from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'credentials@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $supplier = Supplier::create(['name' => 'South upstream', 'code' => 'SOUTH']);
    $batch = CredentialBatch::create(['supplier_id' => $supplier->id, 'reference' => 'SOUTH-01', 'imported_at' => now()]);
    app(ImportCredentials::class)->handle($batch, [['identifier' => 'south-001', 'secret' => 'secret']]);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)->get(route('operations.credentials'))->assertOk()->assertInertia(fn ($page) => $page->where('credentials.total', 0));
});
