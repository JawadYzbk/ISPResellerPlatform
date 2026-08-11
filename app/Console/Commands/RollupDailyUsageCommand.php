<?php

namespace App\Console\Commands;

use App\Actions\RollupDailyUsage;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class RollupDailyUsageCommand extends Command
{
    protected $signature = 'usage:rollup-daily {--date= : Usage date in YYYY-MM-DD format}';

    protected $description = 'Roll FreeRADIUS or polled session counters into daily usage.';

    public function handle(RollupDailyUsage $rollup): int
    {
        $date = $this->option('date') === null
            ? CarbonImmutable::yesterday()
            : CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('date'));
        if ($date === false) {
            $this->error('The --date option must use YYYY-MM-DD format.');

            return self::INVALID;
        }

        Tenant::query()->each(function (Tenant $tenant) use ($rollup, $date): void {
            $count = $rollup->handle($tenant, $date);
            $this->line($tenant->slug.': '.$count.' daily usage row(s) rolled up');
        });

        return self::SUCCESS;
    }
}
