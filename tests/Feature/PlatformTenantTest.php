<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function platformOperatorForTest(): User
{
    $user = User::create([
        'tenant_id' => null,
        'name' => 'Platform Operator',
        'email' => 'platform-'.fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'role' => 'platform_operator',
    ]);

    $user->forceFill(['last_authenticated_at' => now()])->saveQuietly();

    return $user;
}

it('keeps platform tenant administration separate from tenant operators', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $tenantUser = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Tenant Owner',
        'email' => 'owner@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
        'last_authenticated_at' => now(),
    ]);

    $this->actingAs($tenantUser)->get(route('admin.tenants'))->assertForbidden();
});

it('sends a platform operator to the tenant workspace after login', function (): void {
    $platform = platformOperatorForTest();

    $this->post(route('login.store'), ['email' => $platform->email, 'password' => 'password'])
        ->assertRedirect(route('admin.tenants'))
        ->assertSessionHas('success_title', 'Welcome back');

    $this->get(route('admin.tenants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tenants/Index')
            ->has('tenants')
            ->has('currencies')
        );
});

it('treats a super admin as a platform operator with full authorization', function (): void {
    $superAdmin = User::create([
        'tenant_id' => null,
        'name' => 'NexaISP Super Admin',
        'email' => 'superadmin-test@example.test',
        'password' => Hash::make('password'),
        'role' => 'super_admin',
        'last_authenticated_at' => now(),
    ]);

    expect($superAdmin->isSuperAdmin())->toBeTrue()
        ->and($superAdmin->isPlatformOperator())->toBeTrue()
        ->and($superAdmin->can('settings.manage'))->toBeTrue();

    $this->actingAs($superAdmin)->get(route('admin.tenants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Tenants/Index'));
});

it('provisions a tenant, owner, defaults and an audit record from the platform workspace', function (): void {
    $platform = platformOperatorForTest();

    $response = $this->actingAs($platform)->post(route('admin.tenants.store'), [
        'name' => 'Cedars Fiber',
        'slug' => 'cedars-fiber',
        'locale' => 'en',
        'timezone' => 'Asia/Beirut',
        'base_currency' => 'USD',
        'collection_currency' => 'LBP',
        'owner_name' => 'Nadia Haddad',
        'owner_email' => 'nadia@cedars.test',
        'owner_password' => 'temporary-owner-password',
        'owner_password_confirmation' => 'temporary-owner-password',
    ]);

    $response->assertRedirect(route('admin.tenants'))->assertSessionHas('success_title', 'Tenant created');

    $tenant = Tenant::query()->where('slug', 'cedars-fiber')->firstOrFail();
    $owner = User::query()->where('email', 'nadia@cedars.test')->firstOrFail();
    expect($owner->tenant_id)->toBe($tenant->id)
        ->and($owner->role)->toBe('tenant_owner')
        ->and(Hash::check('temporary-owner-password', (string) $owner->password))->toBeTrue();

    app(Tenancy::class)->run($tenant, function () use ($owner): void {
        expect($owner->hasRole('tenant_owner'))->toBeTrue();
    });

    app(Tenancy::class)->run($tenant, function () use ($tenant): void {
        expect($tenant->branches()->where('code', 'HQ')->exists())->toBeTrue()
            ->and($tenant->zones()->where('code', 'DEFAULT')->exists())->toBeTrue()
            ->and($tenant->currencies()->where('code', 'LBP')->where('is_collection', true)->exists())->toBeTrue();
    });

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'platform',
        'description' => 'Tenant created.',
        'subject_id' => $tenant->id,
        'causer_id' => $platform->id,
    ]);
});

it('updates tenant lifecycle status and records the before and after values', function (): void {
    $platform = platformOperatorForTest();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);

    $response = $this->actingAs($platform)->patch(route('admin.tenants.update', $tenant), [
        'name' => 'Northline Fiber',
        'status' => 'suspended',
    ]);

    $response->assertRedirect(route('admin.tenants'))->assertSessionHas('success_title', 'Tenant updated');
    expect($tenant->refresh()->name)->toBe('Northline Fiber')
        ->and($tenant->status)->toBe('suspended');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'platform',
        'description' => 'Tenant updated.',
        'subject_id' => $tenant->id,
        'causer_id' => $platform->id,
    ]);
});
