<?php

use App\Actions\CheckLedgerInvariants;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\Customer;
use App\Models\LedgerAccount;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
