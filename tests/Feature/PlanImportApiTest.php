<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('imports and rolls back plans through the scoped API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'plans-import@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $token = $user->createToken('plan-importer', ['api'])->plainTextToken;
    $csv = "name,download_kbps,upload_kbps,duration_days,amount_minor,currency\nHome 50,50000,10000,30,2500,USD";

    $response = $this->withToken($token)->postJson('/api/v1/imports/plans', ['filename' => 'plans.csv', 'csv' => $csv]);
    $response->assertCreated()->assertJsonPath('type', 'plans')->assertJsonPath('successful_rows', 1);
    app(Tenancy::class)->set($tenant);
    expect(Plan::count())->toBe(1);

    $this->withToken($token)->postJson('/api/v1/imports/plans/'.$response->json('id').'/rollback')
        ->assertOk()
        ->assertJsonPath('status', 'rolled_back')
        ->assertJsonPath('deleted_plans', 1);
    app(Tenancy::class)->set($tenant);
    expect(Plan::count())->toBe(0);
});

it('rejects plan imports without plan management capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'plans-import-collector@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $token = $user->createToken('plan-importer', ['api'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/imports/plans', [
        'csv' => "name,download_kbps,upload_kbps,duration_days,amount_minor,currency\nHome 50,50000,10000,30,2500,USD",
    ])->assertForbidden();
});
