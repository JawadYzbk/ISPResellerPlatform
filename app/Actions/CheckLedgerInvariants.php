<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\PartnerWallet;
use App\Models\WalletTransaction;

final readonly class CheckLedgerInvariants implements Action
{
    /** @return array{status: string, checked_entries: int, checked_customers: int, checked_wallets: int, violations: list<array<string, mixed>>} */
    public function handle(): array
    {
        $violations = [];
        $entries = JournalEntry::query()->with('lines')->get();
        foreach ($entries as $entry) {
            $totals = [];
            foreach ($entry->lines as $line) {
                $totals[$line->currency] ??= ['debit' => 0, 'credit' => 0];
                $totals[$line->currency]['debit'] += $line->debit_amount;
                $totals[$line->currency]['credit'] += $line->credit_amount;
            }
            foreach ($totals as $currency => $total) {
                if ($total['debit'] !== $total['credit']) {
                    $violations[] = ['type' => 'unbalanced_journal', 'entry_id' => $entry->public_id, 'currency' => $currency];
                }
            }
        }

        $customers = Customer::query()->get();
        foreach ($customers as $customer) {
            $expected = (int) (LedgerEntry::query()->where('customer_id', $customer->id)->latest('id')->value('balance_after') ?? 0);
            if ($customer->balance_amount !== $expected) {
                $violations[] = ['type' => 'customer_projection', 'customer_id' => $customer->public_id, 'expected' => $expected, 'actual' => $customer->balance_amount];
            }
        }

        $wallets = PartnerWallet::query()->get();
        foreach ($wallets as $wallet) {
            $expected = (int) (WalletTransaction::query()->where('wallet_id', $wallet->id)->latest('id')->value('balance_after') ?? 0);
            if ($wallet->balance_amount !== $expected) {
                $violations[] = ['type' => 'partner_wallet_projection', 'wallet_id' => $wallet->id, 'expected' => $expected, 'actual' => $wallet->balance_amount];
            }
        }

        return [
            'status' => $violations === [] ? 'ok' : 'failed',
            'checked_entries' => $entries->count(),
            'checked_customers' => $customers->count(),
            'checked_wallets' => $wallets->count(),
            'violations' => array_slice($violations, 0, 100),
        ];
    }
}
