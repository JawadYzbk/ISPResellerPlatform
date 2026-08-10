<?php

use App\Actions\CreatePartner;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
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
