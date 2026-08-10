<?php

use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Partner;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('posts a balanced journal and projects the customer balance', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $receivable = LedgerAccount::where('code', '1100')->firstOrFail();
    $revenue = LedgerAccount::where('code', '4000')->firstOrFail();

    $entry = app(PostJournalEntry::class)->post('Invoice INV-00001', [
        new JournalLineInput($receivable->id, 'USD', debitAmount: 2500, customerId: $customer->id),
        new JournalLineInput($revenue->id, 'USD', creditAmount: 2500),
    ]);

    expect($entry->lines)->toHaveCount(2)
        ->and($customer->refresh()->balance_amount)->toBe(2500)
        ->and($customer->ledgerEntries()->firstOrFail()->balance_after)->toBe(2500);
});

it('rejects unbalanced entries and protects posted records from mutation', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $receivable = LedgerAccount::where('code', '1100')->firstOrFail();

    expect(fn (): JournalEntry => app(PostJournalEntry::class)->post('Broken', [new JournalLineInput($receivable->id, 'USD', debitAmount: 100)]))
        ->toThrow(DomainException::class);

    $customer = Customer::factory()->create();
    $entry = app(PostJournalEntry::class)->post('Valid', [
        new JournalLineInput($receivable->id, 'USD', debitAmount: 100, customerId: $customer->id),
        new JournalLineInput(LedgerAccount::where('code', '4000')->firstOrFail()->id, 'USD', creditAmount: 100),
    ]);

    expect(fn (): bool => $entry->update(['description' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn (): bool => $entry->delete())->toThrow(LogicException::class);
});

it('attributes commercial journal lines to a partner without changing customer projections', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = Partner::create(['name' => 'Eastline Reseller', 'code' => 'EAST', 'path' => '/', 'currency' => 'USD', 'status' => 'active']);
    $wallets = LedgerAccount::where('code', '1210')->firstOrFail();
    $revenue = LedgerAccount::where('code', '4000')->firstOrFail();

    $entry = app(PostJournalEntry::class)->post('Reseller plan sale', [
        new JournalLineInput($wallets->id, 'USD', debitAmount: 2500),
        new JournalLineInput($revenue->id, 'USD', creditAmount: 2500, partnerId: $partner->id),
    ]);

    $line = $entry->lines->firstWhere('partner_id', $partner->id);

    expect($line)->not->toBeNull()
        ->and($line->partner->is($partner))->toBeTrue()
        ->and($entry->lines->whereNotNull('customer_id'))->toBeEmpty();
});
