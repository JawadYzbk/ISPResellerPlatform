<?php

use App\Enums\NetworkState;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function serviceCreationStaff(Tenant $tenant): User
{
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Staff', 'email' => 'staff-'.$tenant->id.'@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');

    return $user;
}

it('renders the customer service creation form with tenant-scoped options', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = serviceCreationStaff($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['name' => 'Home 50']);
    Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);

    $this->actingAs($user)->get('/customers/'.$customer->public_id.'/services/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Services/Create')->where('customer.public_id', $customer->public_id)->where('plans.0.id', $plan->id)->where('routers.0.name', 'Core'));
});

it('creates a pending tenant-scoped service and records its creation event', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = serviceCreationStaff($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create();
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);

    $response = $this->actingAs($user)->post('/customers/'.$customer->public_id.'/services', [
        'plan_id' => $plan->id,
        'username' => 'ada.home',
        'password' => 'a-secure-service-password',
        'provisioning_mode' => 'radius',
        'router_id' => $router->id,
    ]);
    app(Tenancy::class)->set($tenant);
    $service = Service::query()->firstOrFail();
    $response->assertRedirect('/customers/'.$customer->public_id);
    expect($service->status)->toBe(ServiceStatus::Pending)
        ->and($service->network_state)->toBe(NetworkState::PendingSync)
        ->and($service->customer_id)->toBe($customer->id)
        ->and($service->router_id)->toBe($router->id)
        ->and($service->password_encrypted)->toBe('a-secure-service-password')
        ->and(ServiceEvent::where('service_id', $service->id)->where('event_type', 'created')->exists())->toBeTrue();
});

it('rejects a plan belonging to another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = serviceCreationStaff($tenant);
    $customer = Customer::factory()->create();
    app(Tenancy::class)->set($otherTenant);
    $otherPlan = Plan::factory()->create();
    app(Tenancy::class)->set($tenant);

    $this->actingAs($user)->post('/customers/'.$customer->public_id.'/services', [
        'plan_id' => $otherPlan->id,
        'username' => 'cross-tenant.service',
        'password' => 'a-secure-service-password',
        'provisioning_mode' => 'manual',
    ])->assertSessionHasErrors('plan_id');

    expect(Service::count())->toBe(0);
});
