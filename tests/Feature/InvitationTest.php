<?php

use App\Actions\AcceptInvitation;
use App\Actions\InviteUser;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates a hashed, expiring one-time invitation', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(Tenancy::class)->set($tenant);
    app(CapabilitySeeder::class)->run();
    $owner->assignRole('tenant_owner');

    $result = app(InviteUser::class)->handle($owner, 'new@example.test', 'support_agent');
    $invitation = $result['invitation'];

    expect($invitation->token_hash)->not->toBe($result['token'])
        ->and(app(AcceptInvitation::class)->handle($result['token'], 'New Agent', 'password')->role)->toBe('support_agent')
        ->and(Invitation::findOrFail($invitation->id)->accepted_at)->not->toBeNull()
        ->and(fn (): User => app(AcceptInvitation::class)->handle($result['token'], 'Again', 'password'))->toThrow(DomainException::class);
});
