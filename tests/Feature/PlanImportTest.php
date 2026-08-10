<?php

use App\Actions\ImportPlansCsv;
use App\Actions\RollbackImport;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews plan rows with strict validation without writing plans', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);

    $batch = app(ImportPlansCsv::class)->handle($tenant, implode("\n", [
        'name,download_kbps,upload_kbps,duration_days,amount_minor,currency',
        'Home 50,50000,10000,30,2500,USD',
        'Broken,fast,10000,0,2500,US',
    ]), 'plans.csv', dryRun: true);

    expect($batch->status)->toBe('preview')
        ->and($batch->successful_rows)->toBe(1)
        ->and($batch->failed_rows)->toBe(1)
        ->and($batch->report[1]['errors'])->toContain('download_kbps must be a non-negative integer')
        ->and(Plan::count())->toBe(0);
});

it('imports valid plans and rejects duplicate slugs in the same file', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);

    $batch = app(ImportPlansCsv::class)->handle($tenant, implode("\n", [
        'name,slug,download_kbps,upload_kbps,duration_days,amount_minor,currency,status',
        'Home 50,home-50,50000,10000,30,2500,USD,active',
        'Same slug,home-50,100000,20000,30,4000,USD,active',
    ]), 'plans.csv');

    expect($batch->status)->toBe('completed')
        ->and($batch->successful_rows)->toBe(1)
        ->and($batch->failed_rows)->toBe(1)
        ->and(Plan::count())->toBe(1)
        ->and(Plan::firstOrFail()->slug)->toBe('home-50')
        ->and($batch->report[0]['status'])->toBe('imported')
        ->and($batch->report[0]['plan_id'])->toBe(Plan::firstOrFail()->id)
        ->and($batch->report[1]['errors'])->toContain('slug already exists');
});

it('rolls back imported plans while protecting plans already in use', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $batch = app(ImportPlansCsv::class)->handle($tenant, implode("\n", [
        'name,download_kbps,upload_kbps,duration_days,amount_minor,currency',
        'Home 50,50000,10000,30,2500,USD',
    ]), 'plans.csv');

    expect(app(RollbackImport::class)->handle($batch))->toBe(1)
        ->and($batch->refresh()->status)->toBe('rolled_back')
        ->and(Plan::count())->toBe(0);

    $inUseBatch = app(ImportPlansCsv::class)->handle($tenant, implode("\n", [
        'name,download_kbps,upload_kbps,duration_days,amount_minor,currency',
        'Business 100,100000,20000,30,5000,USD',
    ]), 'plans.csv');
    $plan = Plan::query()->where('slug', 'business-100')->firstOrFail();
    $plan->services()->create([
        'customer_id' => \App\Models\Customer::factory()->create()->id,
        'username' => 'business.100',
        'status' => 'active',
        'provisioning_mode' => 'manual',
        'network_state' => 'in_sync',
    ]);

    expect(fn (): int => app(RollbackImport::class)->handle($inUseBatch))
        ->toThrow(\DomainException::class, 'already assigned to a service');
});
