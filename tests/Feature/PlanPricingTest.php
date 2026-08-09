<?php

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps historical plan prices immutable by versioning effective prices', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);

    app(Tenancy::class)->run($tenant, function (): void {
        $plan = Plan::create(['name' => 'Home 50', 'slug' => 'home-50', 'download_kbps' => 50_000, 'upload_kbps' => 10_000, 'duration_days' => 30, 'amount_minor' => 3500, 'currency' => 'USD']);
        PlanPrice::create(['plan_id' => $plan->id, 'currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
        PlanPrice::create(['plan_id' => $plan->id, 'currency' => 'USD', 'amount_minor' => 4000, 'effective_from' => now()->addDay()]);
    });

    app(Tenancy::class)->set($tenant);
    $plan = Plan::firstOrFail();

    expect($plan->priceAt(now())->amount_minor)->toBe(3500)
        ->and($plan->priceAt(now()->addDays(2))->amount_minor)->toBe(4000)
        ->and(PlanPrice::query()->pluck('amount_minor')->all())->toContain(3500);
});
