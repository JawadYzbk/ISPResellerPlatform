<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Models\RecurringExpenseSchedule;
use App\Models\User;

final readonly class CreateRecurringExpenseSchedule implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): RecurringExpenseSchedule
    {
        $category = ExpenseCategory::query()->whereKey($data['expense_category_id'])->where('is_active', true)->firstOrFail();
        $vendorId = filled($data['expense_vendor_id'] ?? null)
            ? ExpenseVendor::query()->whereKey($data['expense_vendor_id'])->where('is_active', true)->firstOrFail()->id
            : null;

        return RecurringExpenseSchedule::create([
            ...$data,
            'expense_category_id' => $category->id,
            'expense_vendor_id' => $vendorId,
            'created_by_id' => $actor->id,
            'currency' => strtoupper((string) $data['currency']),
            'next_run_on' => $data['starts_on'],
            'is_active' => true,
        ]);
    }
}
