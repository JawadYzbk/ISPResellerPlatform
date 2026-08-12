<?php

use App\Actions\ImportCredentials;
use App\Enums\ProvisioningMode;
use App\Models\CredentialBatch;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Service;
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

it('assigns an available credential through the tenant-safe web action', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'credentials-assign@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $supplier = Supplier::create(['name' => 'Upstream', 'code' => 'UP-01']);
    $batch = CredentialBatch::create(['supplier_id' => $supplier->id, 'reference' => 'BATCH-02', 'imported_at' => now()]);
    app(ImportCredentials::class)->handle($batch, [['identifier' => 'cust-002', 'secret' => 'must-not-render']]);
    $customer = Customer::factory()->create();
    $service = Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => Plan::factory()->create()->id, 'username' => 'upstream-service', 'provisioning_mode' => ProvisioningMode::UpstreamCredential]);
    $credential = UpstreamCredential::firstOrFail();
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('operations.credentials'))
        ->assertInertia(fn ($page) => $page
            ->where('canAssign', true)
            ->where('assignableServices.0.public_id', $service->public_id)
            ->missing('assignableServices.0.secret')
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('operations.credentials.assign', $credential->id), ['service_public_id' => $service->public_id])
        ->assertRedirect(route('operations.credentials'));

    expect($credential->refresh()->assigned_service_id)->toBe($service->id)
        ->and($credential->status->value)->toBe('assigned');
});

it('only offers upstream-credential services as assignment targets', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'credentials-targets@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $supplier = Supplier::create(['name' => 'Upstream', 'code' => 'UP-01']);
    $batch = CredentialBatch::create(['supplier_id' => $supplier->id, 'reference' => 'BATCH-03', 'imported_at' => now()]);
    app(ImportCredentials::class)->handle($batch, [['identifier' => 'cust-003', 'secret' => 'secret']]);
    $manual = Service::factory()->create(['username' => 'manual-service']);
    $upstream = Service::factory()->create(['username' => 'upstream-service', 'provisioning_mode' => ProvisioningMode::UpstreamCredential]);

    $this->actingAs($user)->get(route('operations.credentials'))->assertInertia(fn ($page) => $page
        ->where('assignableServices.0.public_id', $upstream->public_id)
        ->where('assignableServices', fn ($services): bool => collect($services)->pluck('public_id')->doesntContain($manual->public_id))
    );
});
