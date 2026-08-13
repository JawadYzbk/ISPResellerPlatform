<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ExpenseVendor;

final readonly class CreateExpenseVendor implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): ExpenseVendor
    {
        return ExpenseVendor::create([...$data, 'is_active' => true]);
    }
}
