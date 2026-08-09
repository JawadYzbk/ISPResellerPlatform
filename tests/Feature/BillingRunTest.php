<?php

use App\Actions\GenerateInvoices;
use App\Enums\ServiceStatus;
use App\Models\BillingRun;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs prepaid renewal billing idempotently', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => now()->subDay()]);
    $service->plan->prices()->create(['currency' => 'USD', 'amount_minor' => 2500, 'effective_from' => now()->subDay()]);

    $first = app(GenerateInvoices::class)->handle($tenant, CarbonImmutable::today());
    $second = app(GenerateInvoices::class)->handle($tenant, CarbonImmutable::today());

    expect($first->status)->toBe('completed')
        ->and($second->id)->toBe($first->id)
        ->and(BillingRun::count())->toBe(1)
        ->and(Invoice::count())->toBe(1);
});
