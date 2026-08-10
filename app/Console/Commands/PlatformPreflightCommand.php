<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
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

        return is_string($key) && trim($key) !== '';
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

        return $scheme === 'https' && $host !== '' && ! in_array($host, [
            'localhost',
            '127.0.0.1',
            '::1',
            '[::1]',
        ], true);
    }

    private function usesAsynchronousQueue(): bool
    {
        return ! in_array(strtolower((string) config('queue.default')), ['null', 'sync'], true);
    }

    private function usesPersistentCache(): bool
    {
        return ! in_array(strtolower((string) config('cache.default')), ['array', 'null'], true);
    }
}
