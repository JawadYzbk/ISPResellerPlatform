<?php

namespace App\Console\Commands;

use App\Actions\SyncRadiusAccounting;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class SyncRadiusAccountingCommand extends Command
{
    protected $signature = 'radius:sync-accounting';

    protected $description = 'Import FreeRADIUS accounting rows into tenant sessions.';

    public function handle(SyncRadiusAccounting $sync): int
    {
        Tenant::query()->each(function (Tenant $tenant) use ($sync): void {
            $count = $sync->handle($tenant);
            $this->line($tenant->slug.': '.$count.' accounting row(s) synchronized');
        });

        return self::SUCCESS;
    }
}
