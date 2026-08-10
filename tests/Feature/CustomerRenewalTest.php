<?php

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('issues one renewal invoice for a selected customer service', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing Manager', 'email' => 'billing@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->for($customer)->for($plan)->create(['status' => ServiceStatus::Active, 'expires_at' => now()->addDay()]);

    $this->actingAs($user)
        ->get(route('customers.renew', $customer->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Renew')
            ->where('services.0.public_id', $service->public_id)
            ->where('services.0.price.amount_minor', 3500)
        );

    $this->actingAs($user)
        ->post(route('customers.renew.store', $customer->public_id), ['service_id' => $service->public_id])
        ->assertRedirect(route('customers.payments.create', $customer->public_id));

    $this->actingAs($user)
        ->post(route('customers.renew.store', $customer->public_id), ['service_id' => $service->public_id])
        ->assertRedirect(route('customers.payments.create', $customer->public_id));

    app(Tenancy::class)->set($tenant);

    expect(Invoice::count())->toBe(1)
        ->and(Invoice::firstOrFail()->status)->toBe(InvoiceStatus::Issued)
        ->and(Invoice::firstOrFail()->lines()->firstOrFail()->service_id)->toBe($service->id);
});
