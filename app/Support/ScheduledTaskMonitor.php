<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class ScheduledTaskMonitor
{
    public const STARTED_AT_KEY = 'platform:schedule-monitor:started-at';

    /** @var array<string, int> */
    private const TASK_INTERVALS = [
        'routers:reconcile-subscribers' => 900,
        'services:suspend-overdue' => 3600,
        'billing:generate-invoices' => 86400,
        'ledger:check-invariants' => 86400,
    ];

    public function markStarted(?CarbonImmutable $at = null): void
    {
        Cache::add(self::STARTED_AT_KEY, ($at ?? CarbonImmutable::now())->toIso8601String(), now()->addDays(30));
    }

    public function record(string $command, bool $successful, ?CarbonImmutable $at = null): void
    {
        $task = $this->knownTask($command);
        if ($task === null) {
            return;
        }

        $observedAt = ($at ?? CarbonImmutable::now())->toIso8601String();
        $state = Cache::get($this->stateKey($task), []);
        if (! is_array($state)) {
            $state = [];
        }

        $state['last_status'] = $successful ? 'ok' : 'failed';
        $state['last_observed_at'] = $observedAt;
        if ($successful) {
            $state['last_success_at'] = $observedAt;
        } else {
            $state['last_failure_at'] = $observedAt;
        }

        Cache::put($this->stateKey($task), $state, now()->addDays(30));
    }

    /** @return array{status: string, checks: array<string, string>} */
    public function check(?CarbonImmutable $at = null): array
    {
        $now = $at ?? CarbonImmutable::now();
        $startedAt = $this->parse(Cache::get(self::STARTED_AT_KEY));
        if ($startedAt === null) {
            return ['status' => 'unknown', 'checks' => []];
        }

        $checks = [];
        foreach (self::TASK_INTERVALS as $task => $interval) {
            $state = Cache::get($this->stateKey($task), []);
            $lastSuccessAt = is_array($state) ? $this->parse($state['last_success_at'] ?? null) : null;
            $lastStatus = is_array($state) ? ($state['last_status'] ?? null) : null;
            $grace = $this->graceSeconds($interval);

            $status = match (true) {
                $lastStatus === 'failed' => 'failed',
                $lastSuccessAt === null && $startedAt->addSeconds($interval + $grace)->isBefore($now) => 'stale',
                $lastSuccessAt !== null && $lastSuccessAt->addSeconds($interval + $grace)->isBefore($now) => 'stale',
                default => 'ok',
            };
            $checks['scheduled_'.$this->keyName($task)] = $status;
        }

        $statuses = array_values($checks);
        $overall = in_array('failed', $statuses, true)
            ? 'failed'
            : (in_array('stale', $statuses, true) ? 'stale' : 'ok');

        return ['status' => $overall, 'checks' => $checks];
    }

    private function knownTask(string $command): ?string
    {
        foreach (array_keys(self::TASK_INTERVALS) as $task) {
            if (str_contains($command, $task)) {
                return $task;
            }
        }

        return null;
    }

    private function stateKey(string $task): string
    {
        return 'platform:schedule-monitor:'.sha1($task);
    }

    private function keyName(string $task): string
    {
        return str_replace([':', '-'], '_', $task);
    }

    private function graceSeconds(int $interval): int
    {
        return max(300, (int) ceil($interval * 0.25));
    }

    private function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
