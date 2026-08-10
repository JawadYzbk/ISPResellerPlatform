<?php

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

it('renders the tenant service queue with server-side status filters', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'services@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create();
    $active = Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'status' => ServiceStatus::Active, 'username' => 'active-user']);
    Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'status' => ServiceStatus::Suspended, 'username' => 'suspended-user']);

    $this->actingAs($user)
        ->get(route('services.index', ['status' => 'active']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Services/Index')
            ->where('filters.status', 'active')
            ->where('services.total', 1)
            ->where('services.data.0.public_id', $active->public_id)
        );
});
