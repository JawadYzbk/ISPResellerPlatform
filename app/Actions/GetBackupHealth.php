<?php

namespace App\Actions;

use App\Contracts\Action;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;
use Throwable;

final readonly class GetBackupHealth implements Action
{
    public function __construct(private Config $config) {}

    /** @return array{status: 'PASS'|'WARN'|'FAIL', detail: string, checked_at: string, verify_backup: bool, encryption: string, destinations: array<int, array<string, mixed>>} */
    public function handle(): array
    {
        try {
            $destinations = BackupDestinationStatusFactory::createForMonitorConfig($this->config->monitoredBackups)
                ->map(fn (BackupDestinationStatus $status): array => $this->destination($status))
                ->values()
                ->all();
        } catch (Throwable) {
            return [
                'status' => 'FAIL',
                'detail' => 'Backup health could not be checked. Review the server backup configuration.',
                'checked_at' => now()->toIso8601String(),
                'verify_backup' => (bool) config('backup.backup.verify_backup', false),
                'encryption' => (string) config('backup.backup.encryption', 'none'),
                'destinations' => [],
            ];
        }

        $hasFailures = collect($destinations)->contains(fn (array $destination): bool => ! $destination['healthy']);
        $status = $hasFailures
            ? ((string) config('app.env') === 'production' ? 'FAIL' : 'WARN')
            : 'PASS';

        return [
            'status' => $status,
            'detail' => $this->detail($destinations, $hasFailures),
            'checked_at' => now()->toIso8601String(),
            'verify_backup' => (bool) config('backup.backup.verify_backup', false),
            'encryption' => (string) config('backup.backup.encryption', 'none'),
            'destinations' => $destinations,
        ];
    }

    /** @return array<string, mixed> */
    private function destination(BackupDestinationStatus $status): array
    {
        $destination = $status->backupDestination();
        $healthy = $status->isHealthy();
        $backup = $destination->newestBackup();
        $reachable = $destination->connectionError() === null;

        return [
            'name' => $destination->backupName(),
            'disk' => $destination->diskName(),
            'reachable' => $reachable,
            'healthy' => $healthy,
            'backup_count' => $reachable ? $destination->backups()->count() : 0,
            'newest_at' => $backup?->date()->toIso8601String(),
            'newest_age_hours' => $backup instanceof Backup
                ? (int) $backup->date()->diffInHours(now())
                : null,
            'used_storage_bytes' => $reachable ? (int) round($destination->usedStorage()) : 0,
            'failures' => $status->failureMessages()
                ->map(fn (array $failure): array => [
                    'check' => $failure['check'],
                    'message' => $this->safeFailureMessage((string) $failure['check']),
                ])
                ->values()
                ->all(),
        ];
    }

    private function safeFailureMessage(string $check): string
    {
        return match (strtolower($check)) {
            'isreachable' => 'The backup destination is not reachable from the application.',
            'maximumageindays' => 'No recent verified backup archive was found.',
            'maximumstorageinmegabytes' => 'Backup storage is above its configured limit.',
            default => 'A monitored backup check needs attention.',
        };
    }

    /** @param array<int, array<string, mixed>> $destinations */
    private function detail(array $destinations, bool $hasFailures): string
    {
        if ($destinations === []) {
            return 'No backup destinations are configured.';
        }

        if (! $hasFailures) {
            return count($destinations).' backup destination(s) are reachable and pass the monitored health checks.';
        }

        return 'At least one backup destination needs attention. Review the latest archive and server backup logs.';
    }
}
