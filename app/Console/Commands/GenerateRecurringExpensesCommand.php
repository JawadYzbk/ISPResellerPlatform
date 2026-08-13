<?php

namespace App\Console\Commands;

use App\Actions\GenerateDueOperationalExpenses;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Console\Command;

final class GenerateRecurringExpensesCommand extends Command
{
    protected $signature = 'expenses:generate-recurring';

    protected $description = 'Generate pending operational expenses from due recurring schedules';

    public function handle(GenerateDueOperationalExpenses $generate): int
    {
        $count = 0;
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($generate, &$count): void {
            app(Tenancy::class)->run($tenant, function () use ($generate, &$count): void {
                $count += $generate->handle();
            });
        });
        $this->info("Generated {$count} recurring expense(s).");

        return self::SUCCESS;
    }
}
