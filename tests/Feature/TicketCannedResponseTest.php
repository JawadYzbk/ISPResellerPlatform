<?php

use App\Models\Tenant;
use App\Models\TicketCannedResponse;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provisions reusable ticket responses once per tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);

    app(CapabilitySeeder::class)->run();
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);

    expect(TicketCannedResponse::query()->count())->toBe(3)
        ->and(TicketCannedResponse::query()->where('title', 'Payment received')->value('category'))->toBe('billing');
});

it('keeps ticket responses tenant isolated', function (): void {
    $northline = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $southline = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);

    app(Tenancy::class)->set($northline);
    TicketCannedResponse::create(['title' => 'Northline response', 'body' => 'Private Northline note.', 'category' => 'support']);
    app(Tenancy::class)->set($southline);

    expect(TicketCannedResponse::query()->count())->toBe(0);
});
