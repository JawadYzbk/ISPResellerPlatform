<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists, creates, and reads plans through the tenant operator API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'plans-api@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $plan = Plan::factory()->create(['name' => 'Home 50', 'slug' => 'home-50', 'status' => 'active']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $token = $user->createToken('plans-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/plans?filter[status]=active')
        ->assertOk()
        ->assertJsonPath('data.0.id', $plan->public_id)
        ->assertJsonPath('data.0.current_price.amount_minor', 3500);

    $created = $this->withToken($token)->postJson('/api/v1/plans', [
        'name' => 'Home 100',
        'download_kbps' => 100000,
        'upload_kbps' => 20000,
        'duration_days' => 30,
        'amount_minor' => 5000,
        'currency' => 'usd',
        'effective_from' => now()->toDateString(),
        'status' => 'active',
    ])->assertCreated()
        ->assertJsonPath('name', 'Home 100')
        ->json('id');

    app(Tenancy::class)->set($tenant);
    $this->withToken($token)->getJson('/api/v1/plans/'.$created)
        ->assertOk()
        ->assertJsonPath('id', $created)
        ->assertJsonPath('currency', 'USD');
});
