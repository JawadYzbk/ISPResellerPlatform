<?php

use App\Actions\ImportBalancesCsv;
use App\Actions\RollbackImport;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports opening balances through the double-entry journal', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['code' => 'CUS-001']);
    $creditCustomer = Customer::factory()->create(['code' => 'CUS-002']);

    $batch = app(ImportBalancesCsv::class)->handle($tenant, implode("\n", [
        'customer_code,amount_minor,currency,memo',
        'CUS-001,3500,USD,Opening receivable',
        'CUS-002,-500,USD,Opening credit',
    ]), 'balances.csv');

    expect($batch->successful_rows)->toBe(2)
        ->and($customer->refresh()->balance_amount)->toBe(3500)
        ->and($creditCustomer->refresh()->balance_amount)->toBe(-500)
        ->and(JournalEntry::count())->toBe(2)
        ->and($batch->report[0]['status'])->toBe('imported');

    expect(app(RollbackImport::class)->handle($batch))->toBe(2)
        ->and($customer->refresh()->balance_amount)->toBe(0)
        ->and($creditCustomer->refresh()->balance_amount)->toBe(0)
        ->and(JournalEntry::count())->toBe(4)
        ->and($batch->refresh()->status)->toBe('rolled_back');
});

it('previews invalid balances and rejects customers with existing ledger history', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    Customer::factory()->create(['code' => 'CUS-001', 'balance_amount' => 100]);

    $batch = app(ImportBalancesCsv::class)->handle($tenant, implode("\n", [
        'customer_code,amount_minor,currency',
        'CUS-001,3500,USD',
        'CUS-001,1000,USD',
        'MISSING,abc,US',
    ]), 'balances.csv', dryRun: true);

    expect($batch->successful_rows)->toBe(0)
        ->and($batch->failed_rows)->toBe(3)
        ->and($batch->report[0]['errors'])->toContain('customer already has ledger history')
        ->and($batch->report[2]['errors'])->toContain('customer_code does not exist');
});
