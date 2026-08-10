<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ImportBatch;
use App\Models\Tenant;
use InvalidArgumentException;

final readonly class ImportTabularData implements Action
{
    public function __construct(
        private ImportCustomersCsv $customers,
        private ImportPlansCsv $plans,
        private ImportServicesCsv $services,
        private ImportEquipmentCsv $equipment,
        private ImportBalancesCsv $balances,
        private NormalizeTabularImport $normalize,
    ) {}

    public function handle(Tenant $tenant, string $type, string $contents, string $filename, bool $dryRun = false): ImportBatch
    {
        $contents = $this->normalize->handle($contents, $filename);

        return match ($type) {
            'customers' => $this->customers->handle($tenant, $contents, $filename, $dryRun),
            'plans' => $this->plans->handle($tenant, $contents, $filename, $dryRun),
            'services' => $this->services->handle($tenant, $contents, $filename, $dryRun),
            'equipment' => $this->equipment->handle($tenant, $contents, $filename, $dryRun),
            'balances' => $this->balances->handle($tenant, $contents, $filename, $dryRun),
            default => throw new InvalidArgumentException('Unsupported tabular import type.'),
        };
    }
}
