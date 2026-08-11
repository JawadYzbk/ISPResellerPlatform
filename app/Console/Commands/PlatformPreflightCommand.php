<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

final class PlatformPreflightCommand extends Command
{
    protected $signature = 'platform:preflight {--production : Apply production safety checks}';

    protected $description = 'Verify release configuration and database migration readiness.';

    public function handle(Migrator $migrator): int
    {
        $checks = [
            'Application key' => $this->hasApplicationKey(),
            'Database connection' => $this->databaseIsReachable(),
            'Database migrations' => $this->migrationsAreCurrent($migrator),
        ];

        if ((bool) $this->option('production')) {
            $checks += [
                'Production environment' => (string) config('app.env') === 'production',
                'Debug mode disabled' => config('app.debug') === false,
                'Public HTTPS application URL' => $this->hasPublicHttpsUrl(),
                'Secure session cookies' => config('session.secure') === true,
                'Asynchronous queue connection' => $this->usesAsynchronousQueue(),
                'Persistent cache store' => $this->usesPersistentCache(),
                'Capability assignments' => $this->capabilityAssignmentsAreReady(),
                'Sentry configuration' => $this->sentryIsConfigured(),
                'Encrypted off-site backups' => $this->backupsAreConfigured(),
                'Private object storage' => $this->objectStorageIsConfigured(),
                'Monitoring alert routing' => $this->monitoringIsConfigured(),
                'Reverb configuration' => $this->reverbIsConfigured(),
                'WhatsApp configuration' => $this->whatsappIsConfigured(),
                'Payment gateway configuration' => $this->paymentGatewayIsConfigured(),
                'Whish Pay configuration' => $this->whishIsConfigured(),
            ];
        }

        $this->table(
            ['Check', 'Result'],
            collect($checks)->map(fn (bool $passed, string $check): array => [
                $check,
                $passed ? 'PASS' : 'FAIL',
            ])->all(),
        );

        $failures = array_keys(array_filter($checks, static fn (bool $passed): bool => ! $passed));
        if ($failures !== []) {
            $this->error('Preflight failed: '.implode(', ', $failures).'.');

            return self::FAILURE;
        }

        $this->info('Preflight passed.');

        return self::SUCCESS;
    }

    private function hasApplicationKey(): bool
    {
        $key = config('app.key');

        if (! is_string($key) || trim($key) === '') {
            return false;
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }

        return is_string($key) && Encrypter::supported($key, (string) config('app.cipher'));
    }

    private function databaseIsReachable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function migrationsAreCurrent(Migrator $migrator): bool
    {
        try {
            $pendingMigrations = array_diff(
                array_keys($migrator->getMigrationFiles($migrator->paths())),
                $migrator->getRepository()->getRan(),
            );

            return $pendingMigrations === [];
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasPublicHttpsUrl(): bool
    {
        $url = parse_url((string) config('app.url'));
        $host = is_array($url) ? strtolower((string) ($url['host'] ?? '')) : '';
        $scheme = is_array($url) ? strtolower((string) ($url['scheme'] ?? '')) : '';

        return $scheme === 'https' && ! $this->isReservedHost($host);
    }

    private function usesAsynchronousQueue(): bool
    {
        return ! in_array(strtolower((string) config('queue.default')), ['null', 'sync'], true);
    }

    private function usesPersistentCache(): bool
    {
        return ! in_array(strtolower((string) config('cache.default')), ['array', 'null'], true);
    }

    private function capabilityAssignmentsAreReady(): bool
    {
        try {
            $tenancy = app(Tenancy::class);
            foreach (Tenant::query()->get(['id']) as $tenant) {
                $ready = $tenancy->run($tenant, function (): bool {
                    foreach (User::query()->whereNotNull('role')->get() as $user) {
                        if (! $user->hasRole((string) $user->role)) {
                            return false;
                        }
                    }

                    return true;
                });

                if ($ready !== true) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function sentryIsConfigured(): bool
    {
        return $this->hasConfiguredValue(config('sentry.dsn')) && config('sentry.send_default_pii') === false;
    }

    private function backupsAreConfigured(): bool
    {
        $disks = config('backup.backup.destination.disks');
        if (! is_array($disks)) {
            return false;
        }

        $hasOffsiteDisk = collect($disks)->contains(fn (mixed $disk): bool => is_string($disk) && strtolower($disk) !== 'local');

        return $hasOffsiteDisk
            && $this->hasConfiguredValue(config('backup.backup.password'))
            && strtolower((string) config('backup.backup.encryption')) !== 'none'
            && $this->hasConfiguredValue(config('backup.notifications.mail.to'));
    }

    private function objectStorageIsConfigured(): bool
    {
        if ((string) config('filesystems.default') !== 's3') {
            return false;
        }

        return collect(['key', 'secret', 'bucket', 'endpoint'])
            ->every(fn (string $key): bool => $this->hasConfiguredValue(config('filesystems.disks.s3.'.$key)));
    }

    private function reverbIsConfigured(): bool
    {
        if ((string) config('broadcasting.default') !== 'reverb') {
            return true;
        }

        $allowedOrigins = config('reverb.apps.apps.0.allowed_origins');
        $host = config('broadcasting.connections.reverb.options.host');

        return $this->hasConfiguredValue(config('broadcasting.connections.reverb.key'))
            && $this->hasConfiguredValue(config('broadcasting.connections.reverb.secret'))
            && $this->hasConfiguredValue($host)
            && is_array($allowedOrigins)
            && $allowedOrigins !== [];
    }

    private function monitoringIsConfigured(): bool
    {
        if (! (bool) config('monitoring.enabled')) {
            return false;
        }

        $url = config('monitoring.webhook_url');
        $parsed = is_string($url) ? parse_url($url) : false;
        $scheme = is_array($parsed) ? strtolower((string) ($parsed['scheme'] ?? '')) : '';

        return $this->hasConfiguredValue($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array($scheme, ['http', 'https'], true)
            && $this->hasConfiguredValue(config('monitoring.webhook_secret'));
    }

    private function whatsappIsConfigured(): bool
    {
        if ((string) config('services.whatsapp.mode', 'cloud') !== 'web') {
            return true;
        }

        return (bool) config('services.whatsapp.web.enabled')
            && $this->hasConfiguredValue(config('services.whatsapp.web.endpoint'))
            && $this->hasConfiguredValue(config('services.whatsapp.web.token'))
            && $this->hasConfiguredValue(config('services.whatsapp.web.webhook_url'))
            && $this->hasConfiguredValue(config('services.webhooks.secrets.whatsapp_web'));
    }

    private function paymentGatewayIsConfigured(): bool
    {
        if ((string) config('services.payments.driver', 'null') !== 'stripe') {
            return true;
        }

        return collect(['secret', 'publishable_key', 'endpoint', 'webhook_secret'])
            ->every(fn (string $key): bool => $this->hasConfiguredValue(config('services.stripe.'.$key)));
    }

    private function whishIsConfigured(): bool
    {
        if (! (bool) config('services.whish.enabled')) {
            return true;
        }

        if (! in_array((string) config('services.whish.environment', 'sandbox'), ['sandbox', 'production'], true)) {
            return false;
        }

        if (! collect(['channel', 'secret', 'website_url'])
            ->every(fn (string $key): bool => $this->hasConfiguredValue(config('services.whish.'.$key)))) {
            return false;
        }
        if (filter_var((string) config('services.whish.website_url'), FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $configuredUrls = collect(['success_callback_url', 'failure_callback_url', 'success_redirect_url', 'failure_redirect_url'])
            ->map(fn (string $key): mixed => config('services.whish.'.$key))
            ->filter(fn (mixed $url): bool => is_string($url) && trim($url) !== '');
        if ($configuredUrls->contains(fn (mixed $url): bool => ! $this->hasConfiguredValue($url))) {
            return false;
        }
        if ($configuredUrls->contains(fn (mixed $url): bool => filter_var((string) $url, FILTER_VALIDATE_URL) === false)) {
            return false;
        }

        if ((string) config('services.whish.environment', 'sandbox') !== 'production') {
            return true;
        }

        return $this->isPublicHttpsUrl((string) config('services.whish.website_url'))
            && $configuredUrls->every(fn (mixed $url): bool => $this->isPublicHttpsUrl((string) $url));
    }

    private function isPublicHttpsUrl(string $value): bool
    {
        $parsed = parse_url($value);
        $host = is_array($parsed) ? strtolower((string) ($parsed['host'] ?? '')) : '';
        $scheme = is_array($parsed) ? strtolower((string) ($parsed['scheme'] ?? '')) : '';

        return $scheme === 'https' && ! $this->isReservedHost($host);
    }

    private function hasConfiguredValue(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $normalized = strtolower(trim($value));

        return ! in_array($normalized, ['null', 'replace-me', 'change-me', 'placeholder', 'set-me'], true)
            && ! str_contains($normalized, 'example.')
            && ! in_array($normalized, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    }

    private function isReservedHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        return $host === ''
            || in_array($host, ['localhost', '127.0.0.1', '::1', 'example.com', 'example.net', 'example.org', 'example.invalid'], true)
            || str_ends_with($host, '.example')
            || str_ends_with($host, '.example.com')
            || str_ends_with($host, '.example.net')
            || str_ends_with($host, '.example.org')
            || str_ends_with($host, '.invalid')
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }
}
