<?php

use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('previews, schedules, displays, and cancels a service billing anchor', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00', 'Asia/Beirut'));
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline-cycle',
        'base_currency' => 'LBP',
        'collection_currency' => 'LBP',
        'timezone' => 'Asia/Beirut',
    ]);
    app(Tenancy::class)->set($tenant);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'service-cycle-owner@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'LBP']);
    $plan = Plan::factory()->create(['name' => 'Home 50', 'currency' => 'LBP']);
    $plan->prices()->create(['currency' => 'LBP', 'amount_minor' => 31_000, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->for($customer)->for($plan)->create([
        'status' => ServiceStatus::Active,
        'billing_anchor_day' => 1,
        'expires_at' => CarbonImmutable::parse('2026-09-01 20:59:59', 'UTC'),
    ]);

    $this->actingAs($user)
        ->get(route('services.billing-cycle-preview', $service).'?anchor_day=15')
        ->assertOk()
        ->assertJsonPath('anchor_day', 15)
        ->assertJsonPath('billable_days', 14)
        ->assertJsonPath('prorated_amount', 14_000);

    $this->actingAs($user)
        ->post(route('services.billing-cycle.schedule', $service), ['anchor_day' => 15])
        ->assertRedirect(route('services.show', $service));

    $this->actingAs($user)
        ->get(route('services.show', $service))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Services/Show')
            ->where('canChangeBillingCycle', true)
            ->where('service.billing_anchor_day', 1)
            ->where('service.pending_billing_cycle.anchor_day', 15)
            ->where('service.pending_billing_cycle.prorated_amount', 14_000));

    $this->actingAs($user)
        ->delete(route('services.billing-cycle.cancel', $service))
        ->assertRedirect(route('services.show', $service));

    expect($service->refresh()->metadata)->not->toHaveKey('pending_billing_cycle');
});
