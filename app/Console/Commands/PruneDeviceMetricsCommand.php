<?php

namespace App\Console\Commands;

use App\Actions\PruneDeviceMetrics;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class PruneDeviceMetricsCommand extends Command
{
    protected $signature = 'metrics:prune {--days=90 : Number of days of device metrics to retain}';

    protected $description = 'Delete device metrics older than the configured retention window.';

    public function handle(PruneDeviceMetrics $prune): int
    {
        $days = max(1, (int) $this->option('days'));
        Tenant::query()->each(function (Tenant $tenant) use ($days, $prune): void {
            $count = $prune->handle($tenant, $days);
            $this->line($tenant->slug.': '.$count.' device metric(s) pruned');
        });

        return self::SUCCESS;
    }
}
