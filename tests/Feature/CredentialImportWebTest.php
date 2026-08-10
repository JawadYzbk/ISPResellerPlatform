<?php

use App\Models\CredentialBatch;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\UpstreamCredential;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('imports and reveals supplier credentials through guarded web endpoints', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'credential-import@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $supplier = Supplier::create(['name' => 'Upstream', 'code' => 'UP-01']);

    $this->actingAs($user)
        ->post(route('operations.credentials.import'), [
            'supplier_id' => $supplier->id,
            'reference' => 'BATCH-01',
            'expires_at' => now()->addMonth()->toDateString(),
            'file' => UploadedFile::fake()->createWithContent('credentials.csv', "identifier,secret\nuser-001,secret-001\nuser-002,secret-002"),
        ])
        ->assertRedirect(route('operations.credentials'));

    app(Tenancy::class)->set($tenant);
    $batch = CredentialBatch::query()->firstOrFail();
    $credential = UpstreamCredential::query()->where('identifier', 'user-001')->firstOrFail();
    expect($batch->reference)->toBe('BATCH-01')
        ->and(UpstreamCredential::count())->toBe(2)
        ->and($credential->toArray())->not->toHaveKey('secret');

    $this->actingAs($user)
        ->postJson(route('operations.credentials.reveal', $credential->id))
        ->assertOk()
        ->assertJsonPath('secret', 'secret-001');
});

it('rejects credential import without the import capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Viewer', 'email' => 'credential-viewer@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('support_agent');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)->post(route('operations.credentials.import'), [])->assertForbidden();
});
