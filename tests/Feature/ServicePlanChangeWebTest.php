<?php

use App\Actions\ChangeServicePlan;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('shows, previews, and cancels a scheduled plan change from the service surface', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'service-plan-owner@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $oldPlan = Plan::factory()->create(['name' => 'Starter', 'currency' => 'USD']);
    $newPlan = Plan::factory()->create(['name' => 'Pro', 'currency' => 'USD']);
    $service = Service::factory()->for($customer)->for($oldPlan)->create(['status' => ServiceStatus::Active]);
    app(ChangeServicePlan::class)->handle($service, $newPlan, 'next_cycle', $user);

    $this->actingAs($user)
        ->get(route('services.show', $service->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Services/Show')
            ->where('service.pending_plan_change.plan.name', 'Pro')
            ->where('service.pending_plan_change.apply_at', $service->expires_at->toIso8601String())
        );

    $this->actingAs($user)
        ->get(route('services.plan-change-preview', $service->public_id).'?plan_id='.$newPlan->id.'&effective=next_cycle')
        ->assertOk()
        ->assertJsonPath('effective', 'next_cycle')
        ->assertJsonPath('to_plan_id', $newPlan->public_id);

    $this->actingAs($user)
        ->delete(route('services.change-plan.cancel', $service->public_id))
        ->assertRedirect(route('customers.show', $customer->public_id));

    expect($service->refresh()->metadata)->not->toHaveKey('pending_plan_change');
});
