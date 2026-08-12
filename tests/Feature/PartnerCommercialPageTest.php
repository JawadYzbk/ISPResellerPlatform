<?php

use App\Actions\CreatePartner;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('redirects guests to sign in before loading the commercial workspace', function (): void {
    $this->get('/partners/commercial')->assertRedirect(route('login'));
});

it('renders the partner commercial workspace without reseller costs', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'R-005', 'USD');
    $plan = Plan::factory()->create(['name' => 'Home 50', 'slug' => 'home-50', 'currency' => 'USD', 'status' => 'active']);
    $book = PriceBook::create(['partner_id' => $partner->id, 'name' => 'Reseller prices', 'effective_from' => now()->subDay()]);
    PriceBookItem::create(['price_book_id' => $book->id, 'plan_id' => $plan->id, 'currency' => 'USD', 'buy_amount_minor' => 2500, 'sell_amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $user = User::create(['tenant_id' => $tenant->id, 'partner_id' => $partner->id, 'name' => 'Reseller', 'email' => 'commercial-page@example.test', 'password' => Hash::make('password'), 'role' => 'reseller_staff']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('reseller_staff');
    $user->givePermissionTo('wallets.view');

    $this->actingAs($user)->get('/partners/commercial')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Partners/Commercial')->where('catalog.0.sell_amount_minor', 3500)->where('catalog.0.buy_amount_minor', null));
});

it('shows partner setup guidance when an owner has no partner accounts', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'commercial-empty@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->givePermissionTo('wallets.view');

    $this->actingAs($user)->get('/partners/commercial')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Partners/Commercial')->where('selectedPartner', null)->where('partners', []));
});

it('creates a descendant partner from the commercial workspace', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $parent = app(CreatePartner::class)->handle('Parent', 'PARENT', 'USD');
    $user = User::create(['tenant_id' => $tenant->id, 'partner_id' => $parent->id, 'name' => 'Owner', 'email' => 'commercial-create@example.test', 'password' => Hash::make('password'), 'role' => 'reseller_owner']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('reseller_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)->post(route('partners.store'), [
        'name' => 'Child reseller',
        'code' => 'child-001',
        'currency' => 'usd',
        'credit_limit' => 5000,
        'low_balance_threshold' => 1000,
    ])->assertRedirect();

    app(Tenancy::class)->set($tenant);
    $child = Partner::query()->where('code', 'CHILD-001')->firstOrFail();
    expect($child->parent_id)->toBe($parent->id)
        ->and($child->credit_limit)->toBe(5000)
        ->and($child->wallet)->not->toBeNull();
});

it('updates reseller limits and status without changing wallet currency or hierarchy', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $parent = app(CreatePartner::class)->handle('Parent', 'PARENT', 'USD');
    $child = app(CreatePartner::class)->handle('Child', 'CHILD', 'USD', $parent);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'partner_id' => $parent->id,
        'name' => 'Owner',
        'email' => 'commercial-update@example.test',
        'password' => Hash::make('password'),
        'role' => 'reseller_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('reseller_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->patch(route('partners.update', $child), [
            'name' => 'Child Networks',
            'code' => 'CHILD-NETWORKS',
            'credit_limit' => 8000,
            'low_balance_threshold' => 1200,
            'status' => 'suspended',
        ])
        ->assertRedirect(route('partners.commercial', ['partner' => $child->public_id]));

    expect($child->refresh()->only(['name', 'code', 'parent_id', 'currency', 'credit_limit', 'low_balance_threshold', 'status']))
        ->toMatchArray([
            'name' => 'Child Networks',
            'code' => 'CHILD-NETWORKS',
            'parent_id' => $parent->id,
            'currency' => 'USD',
            'credit_limit' => 8000,
            'low_balance_threshold' => 1200,
            'status' => 'suspended',
        ]);
});

it('manages versioned partner price book items from the commercial workspace', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'RESELLER', 'USD');
    $plan = Plan::factory()->create(['name' => 'Home 50', 'slug' => 'home-50', 'currency' => 'USD', 'status' => 'active', 'amount_minor' => 3500]);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'commercial-price-book@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('partners.price-books.items.store', $partner), [
            'plan_id' => $plan->public_id,
            'currency' => 'USD',
            'buy_amount_minor' => 2000,
            'sell_amount_minor' => 3000,
            'min_amount_minor' => 2500,
            'max_amount_minor' => 4000,
            'effective_from' => '2026-08-01',
        ])
        ->assertRedirect(route('partners.commercial', ['partner' => $partner->public_id]));

    $this->actingAs($user)
        ->get(route('partners.commercial', ['partner' => $partner->public_id]))
        ->assertInertia(fn ($page) => $page->where('pricingPlans.0.override.sell_amount_minor', 3000));

    app(Tenancy::class)->set($tenant);
    $first = PriceBookItem::query()->firstOrFail();
    expect($first->buy_amount_minor)->toBe(2000)
        ->and($first->sell_amount_minor)->toBe(3000)
        ->and($first->commissionRule)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('partners.price-books.items.store', $partner), [
            'plan_id' => $plan->public_id,
            'currency' => 'USD',
            'buy_amount_minor' => 2200,
            'sell_amount_minor' => 3200,
            'effective_from' => '2026-09-01',
        ])
        ->assertRedirect();

    app(Tenancy::class)->set($tenant);
    $latest = PriceBookItem::query()->latest('effective_from')->firstOrFail();
    expect(PriceBookItem::query()->count())->toBe(2)
        ->and($first->refresh()->effective_to?->toDateString())->toBe('2026-09-01')
        ->and($latest->sell_amount_minor)->toBe(3200);
});

it('funds a partner wallet and completes settlement actions from the commercial workspace', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'RESELLER', 'USD');
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'commercial-settlement@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->givePermissionTo(['wallets.view', 'wallets.fund', 'settlements.approve']);
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('partners.wallet.fund', $partner), [
            'amount' => 1000,
            'idempotency_key' => '0198d9a4-0e80-72bb-9ef8-44a7bf6c2200',
        ])
        ->assertRedirect(route('partners.commercial', ['partner' => $partner->public_id]))
        ->assertSessionHas('success', 'Partner wallet funded.');

    $this->actingAs($user)
        ->post(route('partners.settlements.store', $partner), [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'currency' => 'USD',
        ])
        ->assertRedirect(route('partners.commercial', ['partner' => $partner->public_id]))
        ->assertSessionHas('success', 'Settlement statement created.');

    app(Tenancy::class)->set($tenant);
    $settlement = Settlement::query()->where('partner_id', $partner->id)->firstOrFail();

    $this->actingAs($user)
        ->post(route('settlements.approve', $settlement))
        ->assertRedirect(route('partners.commercial', ['partner' => $partner->public_id]))
        ->assertSessionHas('success', 'Settlement approved.');

    $this->actingAs($user)
        ->post(route('settlements.pay', $settlement))
        ->assertRedirect(route('partners.commercial', ['partner' => $partner->public_id]))
        ->assertSessionHas('success', 'Settlement paid.');

    app(Tenancy::class)->set($tenant);
    expect($partner->wallet()->value('balance_amount'))->toBe(1000)
        ->and($settlement->refresh()->status)->toBe('paid');
});
