<?php

namespace App\Console\Commands;

use App\Actions\CheckLedgerInvariants;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Console\Command;

final class CheckLedgerInvariantsCommand extends Command
{
    protected $signature = 'ledger:check-invariants';

    protected $description = 'Check journal, customer projection, and partner wallet invariants for every tenant.';

    public function handle(CheckLedgerInvariants $check, Tenancy $tenancy): int
    {
        $failed = false;
        Tenant::query()->each(function (Tenant $tenant) use ($check, $tenancy, &$failed): void {
            $result = $tenancy->run($tenant, fn (): array => $check->handle());
            $this->line($tenant->slug.': '.$result['status'].' ('.count($result['violations']).' violation(s))');
            $failed = $failed || $result['status'] !== 'ok';
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
