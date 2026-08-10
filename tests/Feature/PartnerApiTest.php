<?php

use App\Actions\CreatePartner;
use App\Models\Partner;
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
    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/partners/'.$child->id.'/wallet-top-ups', ['amount' => 1000]);
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/partners/'.$child->id.'/wallet-top-ups', ['amount' => 1000]);

    $first->assertCreated()->assertJsonPath('balance_after', 1000);
    $second->assertCreated()->assertJsonPath('wallet_transaction_id', $first->json('wallet_transaction_id'));
    app(Tenancy::class)->set($tenant);
    expect(Partner::findOrFail($child->id)->wallet->balance_amount)->toBe(1000);
});
