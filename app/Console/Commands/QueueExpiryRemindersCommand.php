<?php

namespace App\Console\Commands;

use App\Actions\QueueExpiryReminders;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class QueueExpiryRemindersCommand extends Command
{
    protected $signature = 'notifications:expiry-reminders {--days=7 : Days before service expiry}';

    protected $description = 'Queue idempotent service-expiry reminders for tenants whose local send hour is active.';

    public function handle(QueueExpiryReminders $reminders): int
    {
        $days = max(1, (int) $this->option('days'));
        Tenant::query()->each(function (Tenant $tenant) use ($days, $reminders): void {
            $count = $reminders->handle($tenant, offsetDays: $days);
            $this->line($tenant->slug.': '.$count.' expiry reminder(s) queued');
        });

        return self::SUCCESS;
    }
}
