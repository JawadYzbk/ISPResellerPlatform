<?php

use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function importOperationsUser(Tenant $tenant): User
{
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Import manager',
        'email' => 'imports@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    return $user;
}

it('previews, commits and rolls back a tenant import from the staff page', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = importOperationsUser($tenant);
    $csv = "first_name,phone\nAda,+96170123456\nBroken,not-a-phone";

    $this->actingAs($user)
        ->get(route('operations.imports'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/Imports')
            ->where('types.0.value', 'customers')
        );

    $this->actingAs($user)->post(route('operations.imports.store'), [
        'type' => 'customers',
        'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv),
        'dry_run' => true,
    ])->assertRedirect(route('operations.imports'));

    app(Tenancy::class)->set($tenant);
    expect(Customer::count())->toBe(0)
        ->and(session('importResult.status'))->toBe('preview')
        ->and(session('importResult.failed_rows'))->toBe(1);

    $this->actingAs($user)->post(route('operations.imports.store'), [
        'type' => 'customers',
        'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv),
        'dry_run' => false,
    ])->assertRedirect(route('operations.imports'));

    app(Tenancy::class)->set($tenant);
    $batch = ImportBatch::query()->where('type', 'customers')->latest('id')->firstOrFail();
    expect($batch->status)->toBe('completed')
        ->and($batch->successful_rows)->toBe(1)
        ->and(Customer::count())->toBe(1);

    $user->forceFill(['last_authenticated_at' => now()])->save();
    $this->actingAs($user)
        ->post(route('operations.imports.rollback', $batch->public_id))
        ->assertRedirect(route('operations.imports'));

    app(Tenancy::class)->set($tenant);
    expect($batch->refresh()->status)->toBe('rolled_back')
        ->and(Customer::count())->toBe(0);
});

it('does not expose the import page without an import capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Viewer', 'email' => 'viewer-imports@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);

    $this->actingAs($user)->get(route('operations.imports'))->assertForbidden();
});
