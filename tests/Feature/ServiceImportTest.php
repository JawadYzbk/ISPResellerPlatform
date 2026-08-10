<?php

use App\Actions\ImportServicesCsv;
use App\Actions\RollbackImport;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews service rows and resolves customer codes and plan slugs', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['code' => 'CUS-001']);
    Plan::factory()->create(['slug' => 'home-50']);

    $batch = app(ImportServicesCsv::class)->handle($tenant, implode("\n", [
        'customer_code,plan_slug,username,status,provisioning_mode,network_state,expires_at',
        'CUS-001,home-50,ada.home,active,radius,pending_sync,2030-01-01',
        'CUS-001,missing,bad username,active,unknown,unknown,2030-01-01',
    ]), 'services.csv', dryRun: true);

    expect($batch->status)->toBe('preview')
        ->and($batch->successful_rows)->toBe(1)
        ->and($batch->failed_rows)->toBe(1)
        ->and($batch->report[0]['data']['customer_id'])->toBe($customer->id)
        ->and($batch->report[1]['errors'])->toContain('plan_slug does not exist')
        ->and(Service::count())->toBe(0);
});

it('imports services and refuses rollback after a billing line references one', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['code' => 'CUS-001']);
    Plan::factory()->create(['slug' => 'home-50']);

    $batch = app(ImportServicesCsv::class)->handle($tenant, implode("\n", [
        'customer_code,plan_slug,username,status,provisioning_mode,network_state',
        'CUS-001,home-50,ada.home,active,radius,in_sync',
    ]), 'services.csv');

    expect($batch->successful_rows)->toBe(1)
        ->and(Service::count())->toBe(1);

    expect(app(RollbackImport::class)->handle($batch))->toBe(1)
        ->and($batch->refresh()->status)->toBe('rolled_back')
        ->and(Service::withTrashed()->count())->toBe(1);

    $secondBatch = app(ImportServicesCsv::class)->handle($tenant, implode("\n", [
        'customer_code,plan_slug,username,status,provisioning_mode,network_state',
        'CUS-001,home-50,ada.billing,active,radius,in_sync',
    ]), 'services.csv');
    $service = Service::query()->where('username', 'ada.billing')->firstOrFail();
    $invoice = Invoice::create(['number' => 'INV-00001', 'customer_id' => $customer->id, 'status' => 'draft', 'currency' => 'USD']);
    $service->invoiceLines()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Home 50',
        'quantity' => 1,
        'unit_amount' => 2500,
        'total_amount' => 2500,
        'currency' => 'USD',
    ]);

    expect(fn (): int => app(RollbackImport::class)->handle($secondBatch))
        ->toThrow(DomainException::class, 'billing history');
});
