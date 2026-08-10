<?php

use App\Models\Addon;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('manages tenant addons and promotions without changing historical plan prices', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'catalog@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $plan = Plan::create(['name' => 'Home 100', 'slug' => 'home-100', 'download_kbps' => 100000, 'upload_kbps' => 20000, 'duration_days' => 30, 'amount_minor' => 3500, 'currency' => 'USD', 'status' => 'active']);
    $price = $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);

    $this->actingAs($user)->post(route('plans.addons.store'), [
        'name' => 'Static IP',
        'slug' => '',
        'description' => 'One public IPv4 address',
        'amount_minor' => 500,
        'currency' => 'usd',
        'billing_period_days' => 30,
        'status' => 'active',
    ])->assertRedirect(route('plans.index'));
    app(Tenancy::class)->set($tenant);
    $addon = Addon::query()->where('slug', 'static-ip')->firstOrFail();

    $this->actingAs($user)->post(route('plans.promotions.store'), [
        'name' => 'Summer discount',
        'code' => 'summer10',
        'type' => 'percent',
        'value' => 1000,
        'applies_to' => [$plan->public_id],
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
        'max_redemptions' => 100,
        'is_active' => true,
    ])->assertRedirect(route('plans.index'));
    app(Tenancy::class)->set($tenant);
    $promotion = Promotion::query()->where('code', 'SUMMER10')->firstOrFail();

    $this->actingAs($user)->put(route('plans.addons.update', $addon->public_id), [
        'name' => 'Static IP', 'slug' => 'static-ip', 'description' => 'Updated', 'amount_minor' => 600, 'currency' => 'USD', 'billing_period_days' => 30, 'status' => 'active',
    ])->assertRedirect(route('plans.index'));
    $this->actingAs($user)->delete(route('plans.promotions.archive', $promotion->public_id))->assertRedirect(route('plans.index'));

    app(Tenancy::class)->set($tenant);
    expect($addon->refresh()->amount_minor)->toBe(600)
        ->and($promotion->refresh()->is_active)->toBeFalse()
        ->and($price->refresh()->amount_minor)->toBe(3500);

    $this->actingAs($user)->get(route('plans.index'))->assertInertia(fn ($page) => $page
        ->where('addons.0.slug', 'static-ip')
        ->where('promotions.0.code', 'SUMMER10')
        ->where('availablePlans.0.public_id', $plan->public_id));
});

it('rejects percentage promotions over one hundred percent', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'catalog-invalid@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)->post(route('plans.promotions.store'), [
        'name' => 'Too much', 'code' => 'TOOMUCH', 'type' => 'percent', 'value' => 10001, 'starts_at' => now()->toDateString(), 'is_active' => true,
    ])->assertSessionHasErrors('value');

    expect(Promotion::query()->count())->toBe(0);
});
