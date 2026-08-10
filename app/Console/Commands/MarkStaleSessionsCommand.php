<?php

namespace App\Console\Commands;

use App\Actions\MarkStaleSessions;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class MarkStaleSessionsCommand extends Command
{
    protected $signature = 'radius:mark-stale-sessions';

    protected $description = 'Close current RADIUS sessions that missed two interim updates.';

    public function handle(MarkStaleSessions $markStale): int
    {
        Tenant::query()->each(function (Tenant $tenant) use ($markStale): void {
            $interval = max(1, (int) ($tenant->settingsData()->settings['radius_interim_interval_seconds'] ?? 300));
            $count = $markStale->handle($tenant, $interval);
            $this->line($tenant->slug.': '.$count.' stale session(s) closed');
        });

        return self::SUCCESS;
    }
}
