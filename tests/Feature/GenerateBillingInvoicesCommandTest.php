<?php

use App\Models\BillingRun;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs the tenant billing invoice command idempotently', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create();
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'status' => 'active', 'expires_at' => now()]);

    $this->artisan('billing:generate-invoices', ['--date' => now()->toDateString()])->assertSuccessful();
    $this->artisan('billing:generate-invoices', ['--date' => now()->toDateString()])->assertSuccessful();

    expect(Invoice::count())->toBe(1)
        ->and(BillingRun::count())->toBe(1);
});
