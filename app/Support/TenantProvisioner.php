<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\LedgerAccount;
use App\Models\Tenant;
use App\Models\Zone;

final class TenantProvisioner
{
    public function provision(Tenant $tenant): void
    {
        app(Tenancy::class)->run($tenant, function () use ($tenant): void {
            $branch = Branch::firstOrCreate(
                ['code' => 'HQ'],
                ['name' => 'Main Office', 'is_default' => true],
            );
            $zone = Zone::firstOrCreate(
                ['code' => 'DEFAULT'],
                ['name' => 'Default Zone'],
            );

            foreach (['customer', 'invoice', 'receipt', 'payment', 'ticket', 'credit_note', 'work_order'] as $key) {
                DocumentSequence::firstOrCreate([
                    'branch_id' => $branch->id,
                    'key' => $key,
                    'period' => now($tenant->timezone)->format('Y'),
                ]);
            }

            $currencyDefinitions = array_fill_keys(['USD', 'EUR', 'LBP'], [
                'is_base' => false,
                'is_collection' => false,
            ]);
            $currencyDefinitions[$tenant->base_currency] = [
                'is_base' => true,
                'is_collection' => $tenant->base_currency === $tenant->collection_currency,
            ];
            $currencyDefinitions[$tenant->collection_currency] = [
                'is_base' => false,
                'is_collection' => true,
            ];
            if ($tenant->base_currency === $tenant->collection_currency) {
                $currencyDefinitions[$tenant->base_currency] = ['is_base' => true, 'is_collection' => true];
            }

            Currency::query()->update(['is_base' => false, 'is_collection' => false]);

            foreach ($currencyDefinitions as $code => $flags) {
                Currency::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $code,
                        'decimal_digits' => $this->decimalDigits($code),
                        ...$flags,
                        'is_active' => true,
                    ],
                );
            }

            foreach ([
                ['code' => '1100', 'name' => 'Accounts Receivable', 'category' => 'asset', 'normal_balance' => 'debit'],
                ['code' => '1000', 'name' => 'Cash', 'category' => 'asset', 'normal_balance' => 'debit'],
                ['code' => '4000', 'name' => 'Service Revenue', 'category' => 'revenue', 'normal_balance' => 'credit'],
                ['code' => '3990', 'name' => 'Opening Balance Equity', 'category' => 'equity', 'normal_balance' => 'credit'],
                ['code' => '4900', 'name' => 'FX Gain/Loss', 'category' => 'income', 'normal_balance' => 'credit'],
                ['code' => '1210', 'name' => 'Partner Wallets', 'category' => 'liability', 'normal_balance' => 'credit'],
                ['code' => '2210', 'name' => 'Partner Commission Payable', 'category' => 'liability', 'normal_balance' => 'credit'],
                ['code' => '5100', 'name' => 'Partner Commission Expense', 'category' => 'expense', 'normal_balance' => 'debit'],
            ] as $account) {
                LedgerAccount::firstOrCreate(['code' => $account['code']], [...$account, 'is_system' => true]);
            }
        });

    }

    private function decimalDigits(string $currency): int
    {
        return strtoupper($currency) === 'LBP' ? 0 : 2;
    }
}
