<?php

namespace App\Console\Commands;

use App\Actions\AutoCloseResolvedTickets;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class AutoCloseResolvedTicketsCommand extends Command
{
    protected $signature = 'tickets:auto-close-resolved';

    protected $description = 'Close resolved tickets after each tenant configured window.';

    public function handle(AutoCloseResolvedTickets $autoClose): int
    {
        Tenant::query()->each(function (Tenant $tenant) use ($autoClose): void {
            $count = $autoClose->handle($tenant);
            $this->line($tenant->slug.': '.$count.' resolved ticket(s) closed');
        });

        return self::SUCCESS;
    }
}
