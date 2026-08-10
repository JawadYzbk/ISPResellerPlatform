<?php

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders plans and creates a plan with its first effective price', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'plans@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('operations_manager');
    $plan = Plan::create(['name' => 'Home 50', 'slug' => 'home-50', 'download_kbps' => 50000, 'upload_kbps' => 10000, 'duration_days' => 30, 'amount_minor' => 3500, 'currency' => 'USD', 'status' => 'active']);
    PlanPrice::create(['plan_id' => $plan->id, 'currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);

    $this->actingAs($user)
        ->get(route('plans.index', ['status' => 'active']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Plans/Index')
            ->where('plans.data.0.name', 'Home 50')
            ->where('plans.data.0.price.amount_minor', 3500)
            ->where('filters.status', 'active')
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('plans.store'), [
            'name' => 'Home 100',
            'slug' => '',
            'download_kbps' => 100000,
            'upload_kbps' => 20000,
            'duration_days' => 30,
            'amount_minor' => 5000,
            'currency' => 'usd',
            'effective_from' => now()->toDateString(),
            'status' => 'active',
        ])
        ->assertRedirect(route('plans.index'));

    app(Tenancy::class)->set($tenant);
    $created = Plan::query()->where('slug', 'home-100')->firstOrFail();
    expect($created->currency)->toBe('USD')
        ->and($created->prices()->firstOrFail()->amount_minor)->toBe(5000);
});

it('does not expose plans from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'plans@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    Plan::create(['name' => 'South 50', 'slug' => 'south-50', 'download_kbps' => 50000, 'upload_kbps' => 10000, 'duration_days' => 30, 'amount_minor' => 3500, 'currency' => 'USD', 'status' => 'active']);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('operations_manager');

    $this->actingAs($user)->get(route('plans.index'))->assertOk()->assertInertia(fn ($page) => $page->where('plans.total', 0));
});
