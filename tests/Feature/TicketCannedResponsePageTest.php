<?php

use App\Models\Tenant;
use App\Models\TicketCannedResponse;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lets a workspace owner manage ticket responses and archive them', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $owner = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Workspace owner',
        'email' => 'ticket-responses-owner@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
        'last_authenticated_at' => now(),
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');
    $owner->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($owner)
        ->get(route('settings.ticket-responses'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/TicketResponses')
            ->where('responses.0.title', 'Investigation in progress')
        );

    $this->actingAs($owner)
        ->post(route('settings.ticket-responses.store'), [
            'title' => 'Need a photo',
            'body' => 'Please send a photo of the router lights so we can continue troubleshooting.',
            'category' => 'support',
        ])
        ->assertRedirect(route('settings.ticket-responses'))
        ->assertSessionHas('success', 'Ticket response created.');

    app(Tenancy::class)->set($tenant);
    $response = TicketCannedResponse::query()->where('title', 'Need a photo')->firstOrFail();

    $this->actingAs($owner)
        ->patch(route('settings.ticket-responses.update', $response->public_id), [
            'title' => 'Need a router photo',
            'body' => 'Please send a clear photo of the router lights.',
            'category' => 'support',
        ])
        ->assertRedirect(route('settings.ticket-responses'));

    $this->actingAs($owner)
        ->delete(route('settings.ticket-responses.archive', $response->public_id))
        ->assertRedirect(route('settings.ticket-responses'))
        ->assertSessionHas('success', 'Ticket response archived.');

    app(Tenancy::class)->set($tenant);
    expect($response->refresh()->is_active)->toBeFalse()
        ->and($response->title)->toBe('Need a router photo');

    $this->actingAs($owner)
        ->patch(route('settings.ticket-responses.update', $response->public_id), [
            'title' => 'Need a router photo',
            'body' => 'Please send a clear photo of the router lights.',
            'category' => 'support',
            'is_active' => true,
        ])
        ->assertRedirect(route('settings.ticket-responses'));

    app(Tenancy::class)->set($tenant);
    expect($response->refresh()->is_active)->toBeTrue();
});

it('does not expose ticket responses to a different tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $owner = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Workspace owner',
        'email' => 'ticket-responses-isolation@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
        'last_authenticated_at' => now(),
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $response = TicketCannedResponse::create(['title' => 'Private Southline', 'body' => 'Private.', 'category' => 'support']);
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');
    $owner->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($owner)
        ->patch(route('settings.ticket-responses.update', $response->public_id), [
            'title' => 'Should fail',
            'body' => 'Should fail.',
            'category' => 'support',
        ])
        ->assertNotFound();
});
