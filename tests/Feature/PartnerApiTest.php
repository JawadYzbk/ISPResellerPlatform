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

it('limits reseller partner APIs to descendants and funds a visible wallet idempotently', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $parent = app(CreatePartner::class)->handle('Parent', 'PARENT', 'USD');
    $child = app(CreatePartner::class)->handle('Child', 'CHILD', 'USD', $parent);
    $sibling = app(CreatePartner::class)->handle('Sibling', 'SIBLING', 'USD');
    $user = User::create(['tenant_id' => $tenant->id, 'partner_id' => $parent->id, 'name' => 'Reseller', 'email' => 'reseller-api@example.test', 'password' => Hash::make('password'), 'role' => 'reseller_owner']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('reseller_owner');
    $token = $user->createToken('partner-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/partners')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissing(['code' => $sibling->code]);
    $headers = ['X-Idempotency-Key' => 'partner-top-up-001'];
    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/partners/'.$child->public_id.'/wallet-top-ups', ['amount' => 1000]);
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/partners/'.$child->public_id.'/wallet-top-ups', ['amount' => 1000]);

    $first->assertCreated()->assertJsonPath('balance_after', 1000);
    $second->assertCreated()->assertJsonPath('wallet_transaction_id', $first->json('wallet_transaction_id'));
    app(Tenancy::class)->set($tenant);
    expect(Partner::findOrFail($child->id)->wallet->balance_amount)->toBe(1000);
});

it('returns a reseller catalog without exposing buy prices', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'R-004', 'USD');
    $plan = Plan::factory()->create(['name' => 'Home 50', 'slug' => 'home-50', 'currency' => 'USD', 'status' => 'active']);
    $book = PriceBook::create(['partner_id' => $partner->id, 'name' => 'Reseller prices', 'effective_from' => now()->subDay()]);
    PriceBookItem::create(['price_book_id' => $book->id, 'plan_id' => $plan->id, 'currency' => 'USD', 'buy_amount_minor' => 2500, 'sell_amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $user = User::create(['tenant_id' => $tenant->id, 'partner_id' => $partner->id, 'name' => 'Reseller', 'email' => 'catalog-api@example.test', 'password' => Hash::make('password'), 'role' => 'reseller_owner']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('reseller_owner');
    $token = $user->createToken('catalog-api', ['api', 'staff:operator'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/partners/'.$partner->public_id.'/catalog');

    $response->assertOk()
        ->assertJsonPath('partner_id', $partner->public_id)
        ->assertJsonPath('data.0.sell_amount_minor', 3500)
        ->assertJsonMissing(['buy_amount_minor' => 2500]);
});

it('exposes the operator settlement lifecycle through the API', function (): void {
    $tenant = Tenant::create(['name' => 'Westline', 'slug' => 'westline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $partner = app(CreatePartner::class)->handle('Reseller', 'R-006', 'USD');
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing', 'email' => 'settlement-api@example.test', 'password' => Hash::make('password'), 'role' => 'billing_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('billing_manager');
    $token = $user->createToken('settlement-api', ['api', 'staff:operator'])->plainTextToken;

    $created = $this->withToken($token)->postJson('/api/v1/partners/'.$partner->public_id.'/settlements', ['period_start' => '2026-08-01', 'period_end' => '2026-08-10', 'currency' => 'usd']);
    $created->assertCreated()->assertJsonPath('status', 'draft');
    $id = $created->json('id');

    $this->withToken($token)->postJson('/api/v1/settlements/'.$id.'/approve')->assertOk()->assertJsonPath('status', 'approved');
    $this->withToken($token)->withHeaders(['X-Idempotency-Key' => 'settlement-pay-001'])->postJson('/api/v1/settlements/'.$id.'/pay')->assertOk()->assertJsonPath('status', 'paid');
});
