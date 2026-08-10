<?php

use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('imports and rolls back services through the scoped API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['code' => 'CUS-001']);
    Plan::factory()->create(['slug' => 'home-50']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'services-import@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $token = $user->createToken('service-importer', ['api', 'staff:operator'])->plainTextToken;
    $csv = "customer_code,plan_slug,username,password,status,provisioning_mode,network_state\nCUS-001,home-50,ada.home,do-not-return,active,radius,in_sync";

    $response = $this->withToken($token)->postJson('/api/v1/imports/services', ['filename' => 'services.csv', 'csv' => $csv]);
    $response->assertCreated()
        ->assertJsonPath('type', 'services')
        ->assertJsonPath('successful_rows', 1)
        ->assertJsonMissingPath('report.0.data.password_encrypted')
        ->assertJsonMissingPath('report.0.data.password')
        ->assertJsonMissingPath('report.0.data.customer_id')
        ->assertJsonMissingPath('report.0.data.plan_id')
        ->assertJsonMissingPath('report.0.service_id');
    app(Tenancy::class)->set($tenant);
    expect(Service::count())->toBe(1)
        ->and(Service::firstOrFail()->customer_id)->toBe($customer->id);
    $batch = ImportBatch::query()->where('public_id', $response->json('id'))->firstOrFail();
    expect($batch->report[0]['data'])->not->toHaveKey('password_encrypted');

    $this->withToken($token)->postJson('/api/v1/imports/services/'.$response->json('id').'/rollback')
        ->assertOk()
        ->assertJsonPath('status', 'rolled_back')
        ->assertJsonPath('deleted_services', 1);
    app(Tenancy::class)->set($tenant);
    expect(Service::withTrashed()->count())->toBe(1);
});

it('rejects service imports without service creation capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'services-import-collector@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $token = $user->createToken('service-importer', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/imports/services', [
        'csv' => "customer_code,plan_slug,username\nCUS-001,home-50,ada.home",
    ])->assertForbidden();
});
