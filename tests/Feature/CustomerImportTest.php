<?php

use App\Actions\ImportCustomersCsv;
use App\Actions\RollbackImport;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews, partially imports, and rolls back a customer CSV', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $csv = "first_name,last_name,phone,email\nAda,Lovelace,+96170123456,ada@example.test\nBroken,Row,not-a-phone,bad-email\nGrace,Hopper,+96171123456,grace@example.test";

    $preview = app(ImportCustomersCsv::class)->handle($tenant, $csv, 'customers.csv', dryRun: true);
    expect($preview->status)->toBe('preview')
        ->and($preview->successful_rows)->toBe(2)
        ->and($preview->failed_rows)->toBe(1)
        ->and(Customer::count())->toBe(0);

    $batch = app(ImportCustomersCsv::class)->handle($tenant, $csv, 'customers.csv');
    expect($batch->status)->toBe('completed')
        ->and($batch->successful_rows)->toBe(2)
        ->and(Customer::count())->toBe(2)
        ->and(collect($batch->report)->where('status', 'imported'))->toHaveCount(2);

    expect(app(RollbackImport::class)->handle($batch))->toBe(2)
        ->and($batch->refresh()->status)->toBe('rolled_back')
        ->and(Customer::count())->toBe(0);
});
