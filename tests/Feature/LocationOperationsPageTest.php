<?php

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function locationManager(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'locations-owner@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    return [$tenant, $user];
}

it('manages tenant branches and hierarchical service zones from the settings surface', function (): void {
    [$tenant, $user] = locationManager();

    $this->actingAs($user)
        ->get(route('settings.locations'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Locations')
            ->where('branches.0.code', 'HQ')
            ->where('branches.0.is_default', true)
            ->where('zones.0.code', 'DEFAULT')
        );

    $this->actingAs($user)
        ->post(route('settings.locations.branches.store'), [
            'name' => 'Main office',
            'code' => 'hq',
            'address' => '12 Cedar Street',
            'phone' => '+961 1 555 010',
        ])
        ->assertRedirect(route('settings.locations'));

    app(Tenancy::class)->set($tenant);
    $branch = Branch::query()->firstOrFail();
    expect($branch->code)->toBe('HQ')->and($branch->is_default)->toBeTrue();

    $this->actingAs($user)
        ->post(route('settings.locations.branches.store'), [
            'name' => 'North office',
            'code' => 'north',
            'is_default' => true,
        ])
        ->assertRedirect(route('settings.locations'));

    app(Tenancy::class)->set($tenant);
    expect($branch->refresh()->is_default)->toBeFalse();
    $secondBranch = Branch::query()->where('code', 'NORTH')->firstOrFail();
    expect($secondBranch->is_default)->toBeTrue();

    $this->actingAs($user)
        ->patch(route('settings.locations.branches.update', $secondBranch), [
            'name' => 'North office updated',
            'code' => 'north-2',
            'is_default' => true,
        ])
        ->assertRedirect(route('settings.locations'));

    $this->actingAs($user)
        ->post(route('settings.locations.zones.store'), [
            'name' => 'North district',
            'code' => 'north',
        ])
        ->assertRedirect(route('settings.locations'));

    app(Tenancy::class)->set($tenant);
    $zone = Zone::query()->where('code', 'NORTH')->firstOrFail();
    $this->actingAs($user)
        ->post(route('settings.locations.zones.store'), [
            'name' => 'Beirut north',
            'code' => 'beirut-north',
            'parent_id' => $zone->id,
        ])
        ->assertRedirect(route('settings.locations'));

    app(Tenancy::class)->set($tenant);
    expect(Zone::query()->where('parent_id', $zone->id)->count())->toBe(1);
    expect($tenant->refresh()->id)->toBe($user->tenant_id);
});

it('does not expose locations to staff without settings capability', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Collector',
        'email' => 'locations-collector@example.test',
        'password' => Hash::make('password'),
        'role' => 'collector',
    ]);

    $this->actingAs($user)->get(route('settings.locations'))->assertForbidden();
});
