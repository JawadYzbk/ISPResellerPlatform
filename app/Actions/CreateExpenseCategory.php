<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;

final readonly class CreateExpenseCategory implements Action
{
    /** @param array{name: string, code: string} $data */
    public function handle(array $data): ExpenseCategory
    {
        $account = LedgerAccount::query()->where('code', '5300')->firstOrFail();

        return ExpenseCategory::create([
            ...$data,
            'code' => strtoupper($data['code']),
            'ledger_account_id' => $account->id,
            'is_active' => true,
        ]);
    }
}
