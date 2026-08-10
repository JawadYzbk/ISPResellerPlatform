<?php

use App\Actions\CheckLedgerInvariants;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\Customer;
use App\Models\LedgerAccount;
use App\Models\Tenant;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('passes ledger invariants for balanced projections', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    app(PostJournalEntry::class)->post('Invariant fixture', [
        new JournalLineInput(LedgerAccount::where('code', '1100')->firstOrFail()->id, 'USD', debitAmount: 100, customerId: $customer->id),
        new JournalLineInput(LedgerAccount::where('code', '4000')->firstOrFail()->id, 'USD', creditAmount: 100),
    ]);

    expect(app(CheckLedgerInvariants::class)->handle())
        ->status->toBe('ok')
        ->violations->toBeEmpty();
});

it('reports a customer projection drift without mutating the ledger', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    app(PostJournalEntry::class)->post('Invariant fixture', [
        new JournalLineInput(LedgerAccount::where('code', '1100')->firstOrFail()->id, 'USD', debitAmount: 100, customerId: $customer->id),
        new JournalLineInput(LedgerAccount::where('code', '4000')->firstOrFail()->id, 'USD', creditAmount: 100),
    ]);
    $customer->forceFill(['balance_amount' => 50])->saveQuietly();

    $result = app(CheckLedgerInvariants::class)->handle();

    expect($result['status'])->toBe('failed')
        ->and(collect($result['violations'])->contains(fn (array $violation): bool => $violation['type'] === 'customer_projection'))->toBeTrue();
});

it('keeps the demo seed ledger projections consistent', function (): void {
    $this->seed(DatabaseSeeder::class);
    $tenant = Tenant::query()->where('slug', 'northline')->firstOrFail();

    $result = app(Tenancy::class)->run($tenant, fn (): array => app(CheckLedgerInvariants::class)->handle());

    expect($result['status'])->toBe('ok')
        ->and($result['violations'])->toBeEmpty()
        ->and(DB::table('customers')->count())->toBe(200)
        ->and(DB::table('services')->count())->toBe(200)
        ->and(DB::table('pops')->count())->toBe(2)
        ->and(DB::table('routers')->count())->toBe(2)
        ->and(DB::table('invoices')->count())->toBe(1200)
        ->and(DB::table('payments')->count())->toBe(1091)
        ->and(DB::table('tickets')->count())->toBe(12)
        ->and(DB::table('work_orders')->count())->toBe(24)
        ->and(DB::table('inventory_units')->count())->toBe(50);
});
