<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ExpenseCategory;

final readonly class UpdateExpenseCategory implements Action
{
    /** @param array{name: string, is_active: bool} $data */
    public function handle(ExpenseCategory $category, array $data): ExpenseCategory
    {
        $category->update($data);

        return $category->refresh();
    }
}
