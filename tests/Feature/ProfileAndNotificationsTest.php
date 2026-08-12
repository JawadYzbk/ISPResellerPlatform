<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function profileAndNotificationsUser(): User
{
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Nadia Haddad',
        'email' => 'nadia@example.test',
        'password' => 'password',
        'role' => 'tenant_owner',
    ]);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    return $user;
}

it('lets a signed-in staff member view and update their profile', function (): void {
    $user = profileAndNotificationsUser();

    $this->actingAs($user)
        ->get(route('profile'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Profile')
            ->where('profile.name', 'Nadia Haddad')
            ->where('profile.email', 'nadia@example.test')
            ->where('profile.role', 'tenant_owner')
        );

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Nadia Beirut',
            'locale' => 'ar',
            'timezone' => 'Asia/Beirut',
        ])
        ->assertRedirect(route('profile'));

    expect($user->refresh()->only(['name', 'locale', 'timezone']))
        ->toBe(['name' => 'Nadia Beirut', 'locale' => 'ar', 'timezone' => 'Asia/Beirut']);
});

it('lets staff open the permission-aware notifications center', function (): void {
    $user = profileAndNotificationsUser();

    $this->actingAs($user)
        ->get(route('notifications'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->where('attentionQueue', [])
        );
});

it('accepts French as a profile language', function (): void {
    $user = profileAndNotificationsUser();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'locale' => 'fr',
            'timezone' => null,
        ])
        ->assertRedirect(route('profile'));

    expect($user->refresh()->locale)->toBe('fr');
});
