<?php

use App\Actions\AccruePartnerCommission;
use App\Actions\ApprovePartnerSettlement;
use App\Actions\CalculatePartnerCommission;
use App\Actions\CreatePartner;
use App\Actions\DebitPartnerWallet;
use App\Actions\FundPartnerWallet;
use App\Actions\GeneratePartnerSettlement;
use App\Actions\PayPartnerSettlement;
use App\Actions\ResolvePartnerPrice;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\JournalLine;
use App\Models\Plan;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves partner-specific prices before the tenant default book', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'R-001', 'USD');
    $plan = Plan::factory()->create(['name' => 'Home 50', 'slug' => 'home-50', 'currency' => 'USD']);
    $rule = CommissionRule::create(['type' => 'margin', 'value' => 0, 'effective_from' => now()->subDay(), 'version' => 4]);
    $defaultBook = PriceBook::create(['name' => 'Default', 'effective_from' => now()->subDay()]);
    $partnerBook = PriceBook::create(['partner_id' => $partner->id, 'name' => 'Reseller', 'effective_from' => now()->subDay()]);
    PriceBookItem::create(['price_book_id' => $defaultBook->id, 'plan_id' => $plan->id, 'commission_rule_id' => $rule->id, 'currency' => 'USD', 'buy_amount_minor' => 2000, 'sell_amount_minor' => 3000, 'effective_from' => now()->subDay()]);
    $selected = PriceBookItem::create(['price_book_id' => $partnerBook->id, 'plan_id' => $plan->id, 'commission_rule_id' => $rule->id, 'currency' => 'USD', 'buy_amount_minor' => 2100, 'sell_amount_minor' => 3200, 'effective_from' => now()->subDay()]);

    $resolved = app(ResolvePartnerPrice::class)->handle($partner, $plan, 'usd');

    expect($resolved->is($selected))->toBeTrue()
        ->and(app(CalculatePartnerCommission::class)->handle($resolved))->toBe(1100);
});

it('calculates percentage and fixed commission rules in minor units', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $plan = Plan::factory()->create(['name' => 'Pro', 'slug' => 'pro', 'currency' => 'USD']);
    $book = PriceBook::create(['name' => 'Default', 'effective_from' => now()->subDay()]);
    $percent = CommissionRule::create(['type' => 'percent', 'value' => 1250, 'effective_from' => now()->subDay(), 'version' => 2]);
    $item = PriceBookItem::create(['price_book_id' => $book->id, 'plan_id' => $plan->id, 'commission_rule_id' => $percent->id, 'currency' => 'USD', 'buy_amount_minor' => 0, 'sell_amount_minor' => 4000, 'effective_from' => now()->subDay()]);

    expect(app(CalculatePartnerCommission::class)->handle($item))->toBe(500);

    $fixed = CommissionRule::create(['type' => 'fixed', 'value' => 275, 'effective_from' => now()->subDay(), 'version' => 3]);
    $item->forceFill(['commission_rule_id' => $fixed->id])->save();

    expect(app(CalculatePartnerCommission::class)->handle($item->refresh()))->toBe(275);
});

it('accrues an immutable commission entry and attributes the payable to the partner', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'R-002', 'USD');
    $plan = Plan::factory()->create(['name' => 'Starter', 'slug' => 'starter', 'currency' => 'USD']);
    $rule = CommissionRule::create(['type' => 'fixed', 'value' => 450, 'effective_from' => now()->subDay(), 'version' => 7]);
    $book = PriceBook::create(['name' => 'Default', 'effective_from' => now()->subDay()]);
    $item = PriceBookItem::create(['price_book_id' => $book->id, 'plan_id' => $plan->id, 'commission_rule_id' => $rule->id, 'currency' => 'USD', 'buy_amount_minor' => 0, 'sell_amount_minor' => 3000, 'effective_from' => now()->subDay()]);

    $entry = app(AccruePartnerCommission::class)->handle($partner, 'renewal', 'renewal-001', $item);
    $replayed = app(AccruePartnerCommission::class)->handle($partner, 'renewal', 'renewal-001', $item);
    $rule->update(['value' => 900, 'version' => 8]);

    expect($replayed->is($entry))->toBeTrue()
        ->and($entry->refresh()->amount_minor)->toBe(450)
        ->and($entry->rule_version)->toBe(7)
        ->and($entry->journal_entry_id)->not->toBeNull()
        ->and(JournalLine::query()->where('partner_id', $partner->id)->sole()->credit_amount)->toBe(450);
});

it('generates, approves, and pays a settlement that reconciles to the partner journal', function (): void {
    $tenant = Tenant::create(['name' => 'Westline', 'slug' => 'westline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'R-003', 'USD');
    app(FundPartnerWallet::class)->handle($partner->wallet()->firstOrFail(), 1000, 'settlement-topup');
    app(DebitPartnerWallet::class)->handle($partner->wallet()->firstOrFail(), 300, 'settlement-renewal');
    $plan = Plan::factory()->create(['name' => 'Business', 'slug' => 'business', 'currency' => 'USD']);
    $rule = CommissionRule::create(['type' => 'fixed', 'value' => 450, 'effective_from' => now()->subDay(), 'version' => 1]);
    $book = PriceBook::create(['name' => 'Default', 'effective_from' => now()->subDay()]);
    $item = PriceBookItem::create(['price_book_id' => $book->id, 'plan_id' => $plan->id, 'commission_rule_id' => $rule->id, 'currency' => 'USD', 'buy_amount_minor' => 0, 'sell_amount_minor' => 3000, 'effective_from' => now()->subDay()]);
    app(AccruePartnerCommission::class)->handle($partner, 'renewal', 'settlement-renewal', $item);
    $start = now()->startOfDay();
    $end = now()->endOfDay();
    $settlement = app(GeneratePartnerSettlement::class)->handle($partner, $start, $end, 'usd');
    $approver = User::factory()->create(['tenant_id' => $tenant->id]);

    $approved = app(ApprovePartnerSettlement::class)->handle($settlement, $approver);
    $paid = app(PayPartnerSettlement::class)->handle($approved, $approver);
    $partnerCredits = (int) JournalLine::query()->where('partner_id', $partner->id)->sum('credit_amount');
    $partnerDebits = (int) JournalLine::query()->where('partner_id', $partner->id)->sum('debit_amount');

    expect($settlement->opening_amount)->toBe(0)
        ->and($settlement->activity_amount)->toBe(700)
        ->and($settlement->closing_amount)->toBe(700)
        ->and($settlement->due_amount)->toBe(450)
        ->and($paid->status)->toBe('paid')
        ->and($paid->journal_entry_id)->not->toBeNull()
        ->and(CommissionEntry::query()->sole()->status)->toBe('settled')
        ->and($partnerCredits - $partnerDebits)->toBe($settlement->closing_amount);
});
