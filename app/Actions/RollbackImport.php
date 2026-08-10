<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\ImportBatch;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class RollbackImport implements Action
{
    public function handle(ImportBatch $batch): int
    {
        if ($batch->type !== 'customers' || $batch->status !== 'completed') {
            throw new DomainException('Only completed customer imports can be rolled back.');
        }

        return DB::transaction(function () use ($batch): int {
            $ids = collect($batch->report ?? [])->pluck('customer_id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
            $deleted = $ids === [] ? 0 : Customer::query()->whereKey($ids)->delete();
            $batch->forceFill(['status' => 'rolled_back', 'rolled_back_at' => now()])->save();

            return $deleted;
        });
    }
}
