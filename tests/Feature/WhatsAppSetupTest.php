<?php

use App\Actions\GetWhatsAppSetupStatus;
use App\Jobs\DeliverMessage;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('shows the server-side WhatsApp Web.js status and QR pairing state', function (): void {
    Http::fake([
        'http://whatsapp-web:3001/accounts/*/status' => Http::response([
            'account_id' => 'isp-manager',
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
    $owner->forceFill(['last_authenticated_at' => now()])->save();

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

it('reuses the tenant account when status is requested outside request middleware', function (): void {
    Http::fake([
        'http://whatsapp-web:3001/accounts/*/status' => Http::response([
            'status' => 'ready',
            'phone' => '96170123456',
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
    app(Tenancy::class)->clear();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->run($tenant, function (): void {
        WhatsAppAccount::create([
            'label' => 'Existing phone',
            'job' => 'billing',
            'bridge_id' => 'existing-account',
            'status' => 'ready',
            'is_active' => true,
        ]);
    });
    app(Tenancy::class)->clear();

    $setup = app(GetWhatsAppSetupStatus::class)->handle(true, $tenant);

    expect($setup['accounts'])->toHaveCount(1)
        ->and($setup['accounts'][0]['label'])->toBe('Existing phone')
        ->and(WhatsAppAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('queues an audited WhatsApp test message only after the bridge is ready', function (): void {
    Queue::fake();
    Http::fake([
        'http://whatsapp-web:3001/accounts/*/status' => Http::response(['account_id' => 'isp-manager', 'status' => 'ready']),
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
        'email' => 'maya-whatsapp-test@example.test',
        'password' => 'password',
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');
    $owner->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($owner)
        ->post(route('settings.whatsapp.test'), ['phone' => '+961 (70) 123-456'])
        ->assertRedirect(route('settings.whatsapp'))
        ->assertSessionHas('success', 'WhatsApp test message queued.');

    app(Tenancy::class)->set($tenant);
    $message = Message::query()->where('channel', 'whatsapp')->firstOrFail();
    expect($message->recipient)->toBe('96170123456')
        ->and($message->metadata['test_notification'])->toBeTrue();
    Queue::assertPushed(DeliverMessage::class);
});

it('creates, reassigns, disconnects, and returns a WhatsApp account to QR pairing', function (): void {
    Http::fake(function ($request) {
        static $statusCalls = 0;
        if (str_ends_with($request->url(), '/disconnect')) {
            return Http::response(['status' => 'qr', 'qr' => 'new-qr']);
        }
        $statusCalls++;

        return Http::response(['status' => $statusCalls === 1 ? 'ready' : 'qr', 'qr' => $statusCalls === 1 ? null : 'new-qr']);
    });
    config()->set([
        'services.whatsapp.mode' => 'web',
        'services.whatsapp.web.enabled' => true,
        'services.whatsapp.web.endpoint' => 'http://whatsapp-web:3001',
        'services.whatsapp.web.token' => 'bridge-token',
    ]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    $owner = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Maya Haddad',
        'email' => 'maya-whatsapp-accounts@example.test',
        'password' => 'password',
        'role' => 'tenant_owner',
        'last_authenticated_at' => now(),
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');
    $owner->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($owner)
        ->post(route('settings.whatsapp.accounts.store'), ['label' => 'Billing phone', 'job' => 'billing'])
        ->assertRedirect(route('settings.whatsapp'));

    app(Tenancy::class)->set($tenant);
    $account = WhatsAppAccount::query()->firstOrFail();
    expect($account->label)->toBe('Billing phone')
        ->and($account->job)->toBe('billing')
        ->and($account->status)->toBe('ready');

    $this->actingAs($owner)
        ->patch(route('settings.whatsapp.accounts.update', $account->public_id), ['label' => 'Collections phone', 'job' => 'collections'])
        ->assertRedirect(route('settings.whatsapp'));

    expect($account->refresh()->label)->toBe('Collections phone')
        ->and($account->job)->toBe('collections');

    $this->actingAs($owner)
        ->post(route('settings.whatsapp.accounts.disconnect', $account->public_id))
        ->assertRedirect(route('settings.whatsapp'));

    expect($account->refresh()->status)->toBe('qr');
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/disconnect'));

    $this->actingAs($owner)
        ->post(route('settings.whatsapp.accounts.store'), ['label' => 'Old phone', 'job' => 'support'])
        ->assertRedirect(route('settings.whatsapp'));

    app(Tenancy::class)->set($tenant);
    $deletable = WhatsAppAccount::query()->where('label', 'Old phone')->firstOrFail();
    $this->actingAs($owner)
        ->delete(route('settings.whatsapp.accounts.delete', $deletable->public_id))
        ->assertRedirect(route('settings.whatsapp'));

    expect(WhatsAppAccount::query()->whereKey($deletable->id)->exists())->toBeFalse();
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE');
});
