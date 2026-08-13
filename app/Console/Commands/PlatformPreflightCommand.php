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
    /** @var array<string, string> */
    private const PRODUCTION_GUIDANCE = [
        'Production environment' => 'Set APP_ENV=production for the release environment.',
        'Debug mode disabled' => 'Set APP_DEBUG=false and clear the application config cache.',
        'Public HTTPS application URL' => 'Set APP_URL to the public HTTPS portal URL.',
        'Secure session cookies' => 'Set SESSION_SECURE_COOKIE=true behind the HTTPS proxy.',
        'Web two-factor enforcement' => 'Set SECURITY_ENFORCE_TWO_FACTOR=true for production.',
        'Privileged staff two-factor enrollment' => 'Finish 2FA enrollment for every privileged tenant operator.',
        'Database credentials' => 'Set a non-placeholder production database URL or password.',
        'Redis credentials' => 'Set non-placeholder Redis credentials when Redis is used for cache, queue, or sessions.',
        'Asynchronous queue connection' => 'Use the database or Redis queue and run a supervised worker.',
        'Persistent cache store' => 'Use a persistent database or Redis cache store.',
        'Sentry configuration' => 'Set SENTRY_LARAVEL_DSN or SENTRY_DSN and keep SENTRY_SEND_DEFAULT_PII=false.',
        'Encrypted off-site backups' => 'Configure an encrypted non-local backup disk, password, and operations recipient.',
        'Private object storage' => 'Configure the private S3-compatible media disk and credentials.',
        'Monitoring alert routing' => 'Enable monitoring and configure its signed HTTP alert webhook.',
        'Reverb configuration' => 'Configure the Reverb key, secret, host, and allowed origins.',
        'WhatsApp configuration' => 'Configure the selected WhatsApp Cloud or Web.js provider and callback secret.',
        'Payment gateway configuration' => 'Configure all Stripe credentials when Stripe is selected.',
        'Whish Pay configuration' => 'Configure Whish merchant credentials and public callback URLs when enabled.',
    ];

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
                'Web two-factor enforcement' => config('security.enforce_web_two_factor') === true,
                'Privileged staff two-factor enrollment' => $this->privilegedStaffTwoFactorIsReady(),
                'Database credentials' => $this->databaseCredentialsAreConfigured(),
                'Redis credentials' => $this->redisCredentialsAreConfigured(),
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
            if (in_array('Capability assignments', $failures, true)) {
                $this->warn('Run php artisan db:seed --class=CapabilitySeeder --force to reconcile tenant roles and permissions.');
            }
            foreach ($failures as $failure) {
                if (isset(self::PRODUCTION_GUIDANCE[$failure])) {
                    $this->line(sprintf(' - %s: %s', $failure, self::PRODUCTION_GUIDANCE[$failure]));
                }
            }
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

    private function databaseCredentialsAreConfigured(): bool
    {
        $connection = (string) config('database.default');
        $database = config('database.connections.'.$connection, []);
        if (! is_array($database) || (string) ($database['driver'] ?? '') === 'sqlite') {
            return true;
        }

        return $this->hasConfiguredValue($database['url'] ?? null)
            || $this->hasConfiguredValue($database['password'] ?? null);
    }

    private function redisCredentialsAreConfigured(): bool
    {
        $usesRedis = in_array(strtolower((string) config('cache.default')), ['redis', 'horizon'], true)
            || strtolower((string) config('queue.default')) === 'redis'
            || strtolower((string) config('session.driver')) === 'redis';
        if (! $usesRedis) {
            return true;
        }

        return $this->hasConfiguredValue(config('database.redis.default.url'))
            || $this->hasConfiguredValue(config('database.redis.default.password'));
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
                $ready = $tenancy->run($tenant, function () use ($tenant): bool {
                    foreach (User::query()->where('tenant_id', $tenant->id)->whereNotNull('role')->get() as $user) {
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

    private function privilegedStaffTwoFactorIsReady(): bool
    {
        try {
            $tenancy = app(Tenancy::class);

            if (User::query()->whereNull('tenant_id')->whereNotNull('role')->where('role', 'platform_operator')->get()
                ->contains(fn (User $user): bool => $user->requiresTwoFactor() && $user->two_factor_confirmed_at === null)) {
                return false;
            }

            foreach (Tenant::query()->get(['id']) as $tenant) {
                $ready = $tenancy->run($tenant, function () use ($tenant): bool {
                    $privilegedUsers = User::query()
                        ->where('tenant_id', $tenant->id)
                        ->whereNotNull('role')
                        ->get();

                    return $privilegedUsers->isNotEmpty()
                        && $privilegedUsers->every(fn (User $user): bool => ! $user->requiresTwoFactor() || $user->two_factor_confirmed_at !== null);
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
        $endpoint = config('services.whish.endpoint');
        if ($this->hasConfiguredValue($endpoint)) {
            $parsedEndpoint = parse_url((string) $endpoint);
            if (! is_array($parsedEndpoint) || filter_var((string) $endpoint, FILTER_VALIDATE_URL) === false) {
                return false;
            }
            if ((string) config('services.whish.environment', 'sandbox') === 'production'
                && strtolower((string) ($parsedEndpoint['scheme'] ?? '')) !== 'https') {
                return false;
            }
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
