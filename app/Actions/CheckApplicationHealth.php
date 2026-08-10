<?php

namespace App\Actions;

use App\Contracts\Action;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final readonly class CheckApplicationHealth implements Action
{
    /** @return array{status: string, checks: array<string, string|int>} */
    public function handle(): array
    {
        $checks = [];
        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'failed';
        }
        try {
            $migrator = app(Migrator::class);
            $pendingMigrations = count(array_diff(
                array_keys($migrator->getMigrationFiles($migrator->paths())),
                $migrator->getRepository()->getRan(),
            ));
            $checks['migrations'] = $pendingMigrations === 0 ? 'ok' : 'pending';
            $checks['migration_pending'] = $pendingMigrations;
        } catch (\Throwable) {
            $checks['migrations'] = 'failed';
            $checks['migration_pending'] = -1;
        }
        try {
            Cache::put('healthcheck', 'ok', 5);
            $checks['cache'] = Cache::get('healthcheck') === 'ok' ? 'ok' : 'failed';
        } catch (\Throwable) {
            $checks['cache'] = 'failed';
        }
        try {
            $queueDepth = Queue::size('default');
            $checks['queue'] = 'ok';
            $checks['queue_depth'] = $queueDepth;
        } catch (\Throwable) {
            $checks['queue'] = 'failed';
            $checks['queue_depth'] = -1;
        }
        $checks['scheduler'] = $this->heartbeat('scheduler_heartbeat');
        $checks['queue_worker'] = $this->heartbeat('queue_worker_heartbeat');

        return ['status' => count(array_intersect(['failed', 'pending', 'stale'], $checks)) > 0 ? 'degraded' : 'ok', 'checks' => $checks];
    }

    private function heartbeat(string $key): string
    {
        $value = Cache::get($key);
        if (! is_string($value)) {
            return 'stale';
        }

        try {
            return CarbonImmutable::parse($value)->greaterThan(now()->subMinutes(5)) ? 'ok' : 'stale';
        } catch (\Throwable) {
            return 'stale';
        }
    }
}
