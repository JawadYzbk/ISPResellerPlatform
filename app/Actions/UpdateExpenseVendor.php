<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ExpenseVendor;

final readonly class UpdateExpenseVendor implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(ExpenseVendor $vendor, array $data): ExpenseVendor
    {
        $vendor->update($data);

        return $vendor->refresh();
    }
}
