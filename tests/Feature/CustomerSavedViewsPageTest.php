<?php

use App\Models\CustomerSavedView;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function customerSavedViewUser(Tenant $tenant, string $role, string $email): User
{
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => ucfirst($role),
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => $role,
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole($role);

    return $user;
}

it('saves, lists, sanitizes and deletes a personal customer view', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = customerSavedViewUser($tenant, 'tenant_owner', 'saved-views-owner@example.test');

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('savedViews', []));

    $this->actingAs($user)
        ->post(route('customers.saved-views.store'), [
            'name' => 'Overdue north zone',
            'filters' => ['status' => 'active', 'zone_id' => '3', 'unexpected' => 'ignored'],
            'columns' => ['zone', 'expiry', 'not-a-column'],
        ])
        ->assertRedirect(route('customers.index'));

    app(Tenancy::class)->set($tenant);
    $view = CustomerSavedView::query()->firstOrFail();
    expect($view->filters)->toBe(['status' => 'active', 'zone_id' => '3'])
        ->and($view->columns)->toBe(['zone', 'expiry']);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('savedViews.0.id', $view->id)
            ->where('savedViews.0.name', 'Overdue north zone')
        );

    $user->forceFill(['last_authenticated_at' => now()])->save();
    $this->actingAs($user)
        ->delete(route('customers.saved-views.destroy', $view))
        ->assertRedirect(route('customers.index'));

    app(Tenancy::class)->set($tenant);
    expect(CustomerSavedView::query()->count())->toBe(0);
});

it('does not allow another operator to delete a personal customer view', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $owner = customerSavedViewUser($tenant, 'tenant_owner', 'saved-views-owner-2@example.test');
    $viewer = customerSavedViewUser($tenant, 'support_agent', 'saved-views-viewer@example.test');

    app(Tenancy::class)->set($tenant);
    $view = CustomerSavedView::create(['user_id' => $owner->id, 'name' => 'Owner queue', 'filters' => [], 'columns' => ['status']]);
    $viewer->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($viewer)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('savedViews', []));

    $this->actingAs($viewer)
        ->delete(route('customers.saved-views.destroy', $view))
        ->assertForbidden();
});
