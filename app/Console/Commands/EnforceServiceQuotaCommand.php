<?php

namespace App\Console\Commands;

use App\Actions\EnforceServiceQuota;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class EnforceServiceQuotaCommand extends Command
{
    protected $signature = 'radius:enforce-quotas';

    protected $description = 'Apply configured service quota/FUP actions from daily usage.';

    public function handle(EnforceServiceQuota $enforce): int
    {
        Tenant::query()->each(function (Tenant $tenant) use ($enforce): void {
            $count = $enforce->handle($tenant);
            $this->line($tenant->slug.': '.$count.' quota action(s) applied');
        });

        return self::SUCCESS;
    }
}
