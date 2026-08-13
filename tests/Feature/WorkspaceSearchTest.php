<?php

use App\Models\Customer;
use App\Models\Incident;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('searches operational records without exposing secrets', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'workspace-search@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['code' => 'CUS-SEARCH-001', 'first_name' => 'Nadia', 'last_name' => 'Haddad']);
    $service = Service::factory()->create(['customer_id' => $customer->id, 'username' => 'nadia.home']);
    $plan = Plan::factory()->create(['name' => 'Lebanon Fiber 100', 'slug' => 'lebanon-fiber-100']);
    Incident::create(['service_id' => $service->id, 'type' => 'service_drift', 'severity' => 'warning', 'status' => 'open', 'title' => 'Nadia drift', 'opened_at' => now()]);

    $this->actingAs($user)
        ->getJson(route('workspace.search', ['q' => 'CUS-SEARCH-001']))
        ->assertOk()
        ->assertJsonPath('results.0.type', 'customer')
        ->assertJsonPath('results.0.href', '/customers/'.$customer->public_id)
        ->assertJsonMissing(['password_encrypted' => $service->password_encrypted]);

    $this->actingAs($user)
        ->getJson(route('workspace.search', ['q' => 'nadia.home']))
        ->assertOk()
        ->assertJsonPath('results.0.type', 'service')
        ->assertJsonPath('results.0.href', '/services/'.$service->public_id);

    $this->actingAs($user)
        ->getJson(route('workspace.search', ['q' => 'settings']))
        ->assertOk()
        ->assertJsonPath('results.0.type', 'page')
        ->assertJsonPath('results.0.localized', true)
        ->assertJsonPath('results.0.href', '/settings/general');

    $this->actingAs($user)
        ->getJson(route('workspace.search', ['q' => 'الفوترة']))
        ->assertOk()
        ->assertJsonPath('results.0.type', 'page')
        ->assertJsonPath('results.0.href', '/billing/invoices');

    $this->actingAs($user)
        ->getJson(route('workspace.search', ['q' => 'paramètres']))
        ->assertOk()
        ->assertJsonPath('results.0.type', 'page')
        ->assertJsonPath('results.0.href', '/settings/general');

    $this->actingAs($user)
        ->getJson(route('workspace.search', ['q' => 'Lebanon Fiber 100']))
        ->assertOk()
        ->assertJsonPath('results.0.type', 'plan')
        ->assertJsonPath('results.0.href', '/plans/'.$plan->public_id.'/edit');
});

it('does not search across tenants', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'workspace-search-isolation@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    Customer::factory()->create(['code' => 'CUS-HIDDEN-001']);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)->getJson(route('workspace.search', ['q' => 'CUS-HIDDEN-001']))->assertOk()->assertJsonPath('results', []);
});
