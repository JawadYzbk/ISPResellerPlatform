<?php

use App\Actions\AssignUpstreamCredential;
use App\Actions\ImportCredentials;
use App\Actions\RevealUpstreamCredential;
use App\Models\CredentialAssignment;
use App\Models\CredentialBatch;
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

it('imports encrypted credentials and assigns each one once', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create(['name' => 'Upstream', 'code' => 'UP-01']);
    $batch = CredentialBatch::create(['supplier_id' => $supplier->id, 'reference' => 'BATCH-01', 'imported_at' => now()]);
    app(ImportCredentials::class)->handle($batch, [['identifier' => 'cust-001', 'secret' => 'plaintext-secret']]);
    $credential = UpstreamCredential::firstOrFail();
    $service = Service::factory()->create();

    app(AssignUpstreamCredential::class)->handle($credential, $service);

    expect($credential->refresh()->status->value)->toBe('assigned')
        ->and($credential->toArray())->not->toHaveKey('secret')
        ->and(CredentialAssignment::count())->toBe(1)
        ->and(fn (): CredentialAssignment => app(AssignUpstreamCredential::class)->handle($credential, Service::factory()->create()))->toThrow(DomainException::class);
});

it('requires capability and recent authentication to reveal a credential', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner', 'last_authenticated_at' => now()]);
    app(CapabilitySeeder::class)->run();
    $owner->assignRole('tenant_owner');
    $owner->forceFill(['last_authenticated_at' => now()])->save();
    $supplier = Supplier::create(['name' => 'Upstream', 'code' => 'UP-01']);
    $batch = CredentialBatch::create(['supplier_id' => $supplier->id, 'reference' => 'BATCH-01', 'imported_at' => now()]);
    app(ImportCredentials::class)->handle($batch, [['identifier' => 'cust-001', 'secret' => 'plaintext-secret']]);

    expect(app(RevealUpstreamCredential::class)->handle($owner, UpstreamCredential::firstOrFail()))->toBe('plaintext-secret');
});
