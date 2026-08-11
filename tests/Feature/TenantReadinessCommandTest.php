<?php

use App\Actions\GetTenantReadiness;
use App\Models\ExchangeRate;
use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MessageTemplateProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('reports a tenant readiness checklist and allows optional integration warnings', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'pilot-tenant']);
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'tenant_owner',
    ]);

    app(Tenancy::class)->run($tenant, function () use ($owner): void {
        Role::findOrCreate('tenant_owner', 'web');
        Permission::findOrCreate('settings.manage', 'web');
        Permission::findOrCreate('customers.view', 'web');
        Role::findByName('tenant_owner', 'web')->syncPermissions(['settings.manage', 'customers.view']);
        $owner->assignRole('tenant_owner');

        $plan = Plan::create([
            'name' => 'Pilot Home',
            'slug' => 'pilot-home',
            'download_kbps' => 25_000,
            'upload_kbps' => 5_000,
            'duration_days' => 30,
            'amount_minor' => 2500,
            'currency' => 'USD',
            'status' => 'active',
        ]);
        $plan->prices()->create([
            'currency' => 'USD',
            'amount_minor' => 2500,
            'effective_from' => now()->subDay(),
        ]);
    });

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug])
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Tenant readiness passed with warnings.')
        ->expectsOutputToContain('Notification templates')
        ->expectsOutputToContain('Tenant logo');
});

it('passes the notification template check after localized defaults are provisioned', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'templates-ready-tenant']);

    app(MessageTemplateProvisioner::class)->provision($tenant);

    expect(app(Tenancy::class)->run($tenant, fn (): int => MessageTemplate::query()->count()))->toBe(27);

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('All 27 active en notification templates are provisioned.');
});

it('fails owner readiness when the assigned role has lost critical capabilities', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'missing-capabilities-tenant']);
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'tenant_owner',
    ]);

    app(Tenancy::class)->run($tenant, function () use ($owner): void {
        Role::findOrCreate('tenant_owner', 'web');
        $owner->assignRole('tenant_owner');
    });

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug, '--json' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Owner account needs its capability role and critical settings');
});

it('fails when a tenant has no billable plan', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'incomplete-tenant']);

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Billable plan')
        ->expectsOutputToContain('Tenant readiness failed.');
});

it('warns when the configured tenant logo is missing from storage', function (): void {
    Config::set('filesystems.default', 's3');
    Storage::fake('s3');

    $tenant = Tenant::factory()->create([
        'slug' => 'missing-logo-tenant',
        'logo_path' => 'tenants/missing-logo/logo.svg',
    ]);

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug, '--json' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('The tenant logo path is configured, but the stored file is missing from the s3 storage disk.');
});

it('passes the tenant logo check when the configured file exists', function (): void {
    Config::set('filesystems.default', 's3');
    Storage::fake('s3');
    Storage::disk('s3')->put('tenants/ready-logo/logo.svg', '<svg />');

    $tenant = Tenant::factory()->create([
        'slug' => 'ready-logo-tenant',
        'logo_path' => 'tenants/ready-logo/logo.svg',
    ]);

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug, '--json' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('A tenant logo is available on the configured storage disk.');
});

it('does not pass WhatsApp Web.js readiness while the bridge is waiting for pairing', function (): void {
    Config::set([
        'services.whatsapp.mode' => 'web',
        'services.whatsapp.web.enabled' => true,
        'services.whatsapp.web.endpoint' => 'http://whatsapp-web:3001',
        'services.whatsapp.web.token' => 'bridge-token',
        'services.whatsapp.web.webhook_url' => 'http://app/api/v1/webhooks/gateways/whatsapp_web',
        'services.webhooks.secrets.whatsapp_web' => 'webhook-secret',
    ]);
    Http::fake([
        'http://whatsapp-web:3001/status' => Http::response(['status' => 'qr', 'qr' => 'pairing-code']),
    ]);

    $tenant = Tenant::factory()->create(['slug' => 'pairing-tenant']);

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug, '--json' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('The private Web.js bridge is configured but is waiting for account pairing to finish.');
});

it('warns when the collection rate is older than the configured freshness window', function (): void {
    Config::set('services.fx.rate_max_age_hours', 24);
    $tenant = Tenant::factory()->create([
        'slug' => 'stale-rate-tenant',
        'base_currency' => 'USD',
        'collection_currency' => 'LBP',
    ]);

    app(Tenancy::class)->run($tenant, function (): void {
        ExchangeRate::create([
            'base_currency' => 'USD',
            'quote_currency' => 'LBP',
            'rate_numerator' => 90_000,
            'rate_denominator' => 1,
            'effective_from' => now()->subDays(2),
            'source' => 'manual',
        ]);
    });

    $check = app(GetTenantReadiness::class)->handle($tenant)['Collection FX rate'];
    expect($check['status'])->toBe('WARN', $check['detail'])
        ->and($check['detail'])->toContain('It is ');

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug, '--json' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Collection FX rate');
});
