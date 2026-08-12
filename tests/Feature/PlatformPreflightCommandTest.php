<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('passes the baseline preflight when the database and key are ready', function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    $this->artisan('platform:preflight')
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Preflight passed.');
});

it('fails the production preflight for unsafe public configuration', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'local',
        'app.debug' => true,
        'app.url' => 'http://localhost',
        'session.secure' => false,
        'queue.default' => 'sync',
        'cache.default' => 'array',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Preflight failed:');
});

it('rejects reserved placeholder hosts in production URLs', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.example.com',
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Public HTTPS application URL');
});

it('requires web two-factor enforcement for production', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.isp.internal',
        'session.secure' => true,
        'security.enforce_web_two_factor' => false,
        'queue.default' => 'database',
        'cache.default' => 'database',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Web two-factor enforcement');
});

it('requires privileged staff to finish two-factor enrollment for production', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_owner']);
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.isp.internal',
        'session.secure' => true,
        'security.enforce_web_two_factor' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Privileged staff two-factor enrollment');
});

it('passes the production preflight for a production-shaped configuration', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.isp.internal',
        'session.secure' => true,
        'security.enforce_web_two_factor' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
        'database.default' => 'sqlite',
        'sentry.dsn' => 'https://public@sentry.io/123',
        'sentry.send_default_pii' => false,
        'backup.backup.destination.disks' => ['s3'],
        'backup.backup.password' => 'backup-passphrase',
        'backup.backup.encryption' => 'aes256',
        'backup.notifications.mail.to' => 'ops@isp.test',
        'filesystems.default' => 's3',
        'filesystems.disks.s3.key' => 's3-key',
        'filesystems.disks.s3.secret' => 's3-secret',
        'filesystems.disks.s3.bucket' => 'isp-media',
        'filesystems.disks.s3.endpoint' => 'https://objects.provider.test',
        'monitoring.enabled' => true,
        'monitoring.webhook_url' => 'https://alerts.isp.internal/platform',
        'monitoring.webhook_secret' => 'monitoring-secret',
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'reverb-key',
        'broadcasting.connections.reverb.secret' => 'reverb-secret',
        'broadcasting.connections.reverb.options.host' => 'realtime.provider.test',
        'reverb.apps.apps.0.allowed_origins' => ['https://portal.provider.test'],
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Preflight passed.');
});

it('rejects placeholder database and Redis credentials in production', function (): void {
    $originalDatabaseConnection = DB::getDefaultConnection();

    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.isp.internal',
        'session.secure' => true,
        'session.driver' => 'redis',
        'queue.default' => 'redis',
        'cache.default' => 'redis',
        'database.default' => 'pgsql',
        'database.connections.pgsql.driver' => 'pgsql',
        'database.connections.pgsql.password' => 'change-me',
        'database.redis.default.url' => null,
        'database.redis.default.password' => 'change-me',
    ]);

    try {
        $this->artisan('platform:preflight', ['--production' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Database credentials')
            ->expectsOutputToContain('Redis credentials');
    } finally {
        DB::purge('pgsql');
        DB::setDefaultConnection($originalDatabaseConnection);
        config()->set('database.default', $originalDatabaseConnection);
    }
});

it('requires monitoring alert routing in production', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.isp.internal',
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
        'monitoring.enabled' => false,
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Monitoring alert routing');
});

it('rejects a placeholder application key', function (): void {
    config()->set('app.key', 'base64:placeholder');

    $this->artisan('platform:preflight')
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Application key');
});

it('rejects a production tenant with an unassigned capability role', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_owner']);
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.example.com',
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('CapabilitySeeder')
        ->expectsOutputToContain('Capability assignments');
});

it('requires the private Web.js bridge and callback secret when selected', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.example.com',
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
        'services.whatsapp.mode' => 'web',
        'services.whatsapp.web.enabled' => true,
        'services.whatsapp.web.endpoint' => 'http://whatsapp-web:3001',
        'services.whatsapp.web.token' => '',
        'services.whatsapp.web.webhook_url' => 'https://portal.example.com/api/v1/webhooks/gateways/whatsapp_web',
        'services.webhooks.secrets.whatsapp_web' => '',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('WhatsApp configuration');
});

it('requires Stripe credentials and a webhook secret when selected', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.example.com',
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
        'services.payments.driver' => 'stripe',
        'services.stripe.secret' => 'sk_test_123',
        'services.stripe.publishable_key' => '',
        'services.stripe.endpoint' => 'https://api.stripe.com',
        'services.stripe.webhook_secret' => '',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Payment gateway configuration');
});

it('requires Whish credentials and public URLs when selected for production', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.isp.test',
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
        'services.whish.enabled' => true,
        'services.whish.environment' => 'production',
        'services.whish.channel' => 'channel',
        'services.whish.secret' => '',
        'services.whish.website_url' => 'https://portal.isp.test',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Whish Pay configuration');
});

it('rejects an invalid production Whish endpoint', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://portal.isp.test',
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'database',
        'services.whish.enabled' => true,
        'services.whish.environment' => 'production',
        'services.whish.channel' => 'channel',
        'services.whish.secret' => 'secret',
        'services.whish.website_url' => 'https://portal.isp.test',
        'services.whish.endpoint' => 'http://whish.internal.test/api',
    ]);

    $this->artisan('platform:preflight', ['--production' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Whish Pay configuration');
});
