<?php

namespace App\Console\Commands;

use App\Actions\SuspendOverdueServices;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class SuspendOverdueServicesCommand extends Command
{
    protected $signature = 'services:suspend-overdue';

    protected $description = 'Suspend active services whose billing period has expired.';

    public function handle(SuspendOverdueServices $suspend): int
    {
        Tenant::query()->each(function (Tenant $tenant) use ($suspend): void {
            $count = $suspend->handle($tenant);
            $this->line($tenant->slug.': '.$count.' service(s) suspended');
        });

        return self::SUCCESS;
    }
}
