<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\JournalEntry;
use App\Models\Plan;
use App\Models\Service;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class RollbackImport implements Action
{
    public function __construct(private PostJournalEntry $journal) {}

    public function handle(ImportBatch $batch): int
    {
        if (! in_array($batch->type, ['customers', 'plans', 'services', 'equipment', 'balances'], true) || $batch->status !== 'completed') {
            throw new DomainException('Only completed customer, plan, service, equipment or balance imports can be rolled back.');
        }

        return DB::transaction(function () use ($batch): int {
            $ids = collect($batch->report ?? [])->pluck('customer_id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
            if ($batch->type === 'plans') {
                $ids = collect($batch->report ?? [])->pluck('plan_id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
                $plansInUse = $ids === [] ? false : Plan::query()->whereKey($ids)->whereHas('services')->exists();
                if ($plansInUse) {
                    throw new DomainException('An imported plan is already assigned to a service and cannot be rolled back.');
                }
                $deleted = $ids === [] ? 0 : Plan::query()->whereKey($ids)->delete();
            } elseif ($batch->type === 'services') {
                $ids = collect($batch->report ?? [])->pluck('service_id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
                $servicesInUse = $ids === [] ? false : Service::query()->whereKey($ids)->whereHas('invoiceLines')->exists();
                if ($servicesInUse) {
                    throw new DomainException('An imported service is already referenced by billing history and cannot be rolled back.');
                }
                $deleted = $ids === [] ? 0 : Service::query()->whereKey($ids)->delete();
            } elseif ($batch->type === 'equipment') {
                $ids = collect($batch->report ?? [])->pluck('inventory_unit_id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
                $unitsInUse = $ids === [] ? false : InventoryMovement::query()->whereIn('inventory_unit_id', $ids)->exists();
                if ($unitsInUse) {
                    throw new DomainException('An imported equipment unit is already referenced by inventory movement and cannot be rolled back.');
                }
                $deleted = $ids === [] ? 0 : InventoryUnit::query()->whereKey($ids)->delete();
            } elseif ($batch->type === 'balances') {
                $reversed = 0;
                /** @var list<array<string, mixed>> $report */
                $report = $batch->report ?? [];
                foreach ($report as $index => $row) {
                    if (! isset($row['journal_entry_id'])) {
                        continue;
                    }
                    $entry = JournalEntry::query()->with('lines')->findOrFail((int) $row['journal_entry_id']);
                    $lines = [];
                    foreach ($entry->lines as $line) {
                        $lines[] = new JournalLineInput($line->account_id, $line->currency, debitAmount: $line->credit_amount, creditAmount: $line->debit_amount, customerId: $line->customer_id, memo: 'Rollback of import '.$batch->public_id);
                    }
                    $reversal = $this->journal->post('Rollback balance import '.$batch->public_id, $lines, sourceType: ImportBatch::class, sourceId: $batch->id.':rollback:'.$entry->id);
                    $report[$index]['reversal_journal_entry_id'] = $reversal->id;
                    $reversed++;
                }
                $batch->report = $report;
                $deleted = $reversed;
            } else {
                $deleted = $ids === [] ? 0 : Customer::query()->whereKey($ids)->delete();
            }
            $batch->forceFill(['status' => 'rolled_back', 'rolled_back_at' => now()])->save();

            return $deleted;
        });
    }
}
