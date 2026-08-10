<?php

use App\Actions\CreatePartner;
use App\Actions\DebitPartnerWallet;
use App\Actions\FundPartnerWallet;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Partner;
use App\Models\Tenant;
use App\Models\WalletTransaction;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a hierarchy and limits visibility to descendants', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $parent = app(CreatePartner::class)->handle('Parent', 'PARENT', 'USD');
    $child = app(CreatePartner::class)->handle('Child', 'CHILD', 'USD', $parent);
    $sibling = app(CreatePartner::class)->handle('Sibling', 'SIBLING', 'USD');

    expect($child->refresh()->path)->toStartWith($parent->refresh()->path)
        ->and(Partner::query()->descendants($parent)->pluck('id')->all())->toContain($child->id)
        ->and(Partner::query()->descendants($parent)->pluck('id')->all())->not->toContain($sibling->id);
});

it('funds and debits a wallet with journal references and idempotent replays', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'R-001', 'USD', creditLimit: 0);
    $wallet = $partner->wallet()->firstOrFail();
    $funded = app(FundPartnerWallet::class)->handle($wallet, 1000, 'wallet-topup-001');
    $replayed = app(FundPartnerWallet::class)->handle($wallet, 1000, 'wallet-topup-001');
    $debited = app(DebitPartnerWallet::class)->handle($wallet, 400, 'wallet-debit-001');

    expect($replayed->id)->toBe($funded->id)
        ->and($debited->balance_after)->toBe(600)
        ->and($wallet->refresh()->balance_amount)->toBe(600)
        ->and(WalletTransaction::count())->toBe(2)
        ->and(JournalEntry::count())->toBe(2)
        ->and(JournalLine::query()->where('partner_id', $partner->id)->count())->toBe(2);
});

it('blocks debit beyond the partner credit limit before journal posting', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'R-002', 'USD', creditLimit: 100);

    expect(fn (): WalletTransaction => app(DebitPartnerWallet::class)->handle($partner->wallet()->firstOrFail(), 101, 'wallet-debit-002'))->toThrow(DomainException::class)
        ->and(WalletTransaction::count())->toBe(0);
});
