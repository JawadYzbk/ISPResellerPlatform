<?php

namespace App\Console\Commands;

use App\Actions\GenerateInvoices;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class GenerateBillingInvoicesCommand extends Command
{
    protected $signature = 'billing:generate-invoices {--date= : Billing date in YYYY-MM-DD format}';

    protected $description = 'Generate the idempotent prepaid renewal invoice run for every tenant.';

    public function handle(GenerateInvoices $generate): int
    {
        $period = CarbonImmutable::parse($this->option('date') ?: now()->toDateString());
        Tenant::query()->each(function (Tenant $tenant) use ($generate, $period): void {
            $run = $generate->handle($tenant, $period);
            $this->line($tenant->slug.': '.$run->processed_count.' invoice(s) generated, '.$run->failed_count.' failed');
        });

        return self::SUCCESS;
    }
}
