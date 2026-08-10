<?php

use App\Actions\CalculatePartnerCommission;
use App\Actions\CreatePartner;
use App\Actions\ResolvePartnerPrice;
use App\Models\CommissionRule;
use App\Models\Plan;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Tenant;
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
