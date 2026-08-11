<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('shows the server-side WhatsApp Web.js status and QR pairing state', function (): void {
    Http::fake([
        'http://whatsapp-web:3001/status' => Http::response([
            'status' => 'qr',
            'qr' => 'qr-payload-for-northline',
            'lastError' => null,
            'readyAt' => null,
        ]),
    ]);
    config()->set([
        'services.whatsapp.mode' => 'web',
        'services.whatsapp.web.enabled' => true,
        'services.whatsapp.web.endpoint' => 'http://whatsapp-web:3001',
        'services.whatsapp.web.token' => 'bridge-token',
        'services.whatsapp.web.webhook_url' => 'http://app/api/v1/webhooks/gateways/whatsapp_web',
        'services.webhooks.secrets.whatsapp_web' => 'webhook-secret',
    ]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    $owner = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Maya Haddad',
        'email' => 'maya-whatsapp@example.test',
        'password' => 'password',
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');

    $this->actingAs($owner)
        ->get(route('settings.whatsapp'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/WhatsApp')
            ->where('setup.mode', 'web')
            ->where('setup.configured', true)
            ->where('setup.status', 'qr')
            ->where('setup.webhook_configured', true)
            ->where('setup.qr_code', fn (string $value): bool => str_starts_with($value, 'data:image/svg+xml'))
        );

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer bridge-token'));
});
