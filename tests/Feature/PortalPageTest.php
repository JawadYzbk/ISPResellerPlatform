<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the customer portal entry and dashboard pages', function (): void {
    Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);

    $this->get('/portal/northline')->assertOk()->assertInertia(fn ($page) => $page->component('Portal/SignIn')->where('tenant.slug', 'northline'));
    $this->get('/portal/northline/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('Portal/Dashboard'));
});
