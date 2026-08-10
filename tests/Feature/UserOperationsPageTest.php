<?php

use App\Actions\InviteUser;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists tenant users, creates a one-time invite, and accepts it', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');
    $owner->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($owner)
        ->get(route('settings.users'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Users')
            ->where('users.total', 1)
            ->where('users.data.0.email', 'owner@example.test')
            ->where('roles.0', 'operations_manager')
        );

    app(Tenancy::class)->set($tenant);
    $response = $this->actingAs($owner)->post(route('settings.users.invite'), [
        'email' => 'agent@example.test',
        'role' => 'support_agent',
    ]);
    $response->assertRedirect(route('settings.users'));

    app(Tenancy::class)->set($tenant);
    $page = $this->actingAs($owner)->get(route('settings.users'));
    $page->assertInertia(fn ($inertia) => $inertia
        ->where('invitation.email', 'agent@example.test')
        ->where('invitation.role', 'support_agent')
        ->has('invitation.token')
    );

    app(Tenancy::class)->set($tenant);
    $invitation = Invitation::query()->where('email', 'agent@example.test')->firstOrFail();
    $token = app(InviteUser::class)->handle($owner, 'second@example.test', 'cashier')['token'];
    auth()->logout();
    $this->get(route('invitations.show', $token))->assertOk()->assertInertia(fn ($inertia) => $inertia->component('Auth/AcceptInvitation'));
    $this->post(route('invitations.accept', $token), [
        'name' => 'New Cashier',
        'password' => 'strong-password-123',
        'password_confirmation' => 'strong-password-123',
    ])->assertRedirect(route('login'));

    app(Tenancy::class)->set($tenant);
    expect($invitation->refresh()->accepted_at)->toBeNull()
        ->and(User::query()->where('email', 'new@example.test')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'second@example.test')->exists())->toBeTrue();
});

it('rejects user administration without the management capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'cashier-users@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('cashier');

    $this->actingAs($user)->get(route('settings.users'))->assertForbidden();
});
