<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\LedgerAccount;
use App\Models\PartnerWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

final readonly class FundPartnerWallet implements Action
{
    public function __construct(private PostJournalEntry $journal) {}

    public function handle(PartnerWallet $wallet, int $amount, string $idempotencyKey, ?User $actor = null): WalletTransaction
    {
        abort_if($amount < 1, 422, 'Wallet funding amount must be positive.');
        $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof WalletTransaction) {
            return $existing;
        }

        return DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $actor): WalletTransaction {
            $locked = PartnerWallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $cash = LedgerAccount::query()->where('code', '1000')->firstOrFail();
            $walletAccount = LedgerAccount::query()->where('code', '1210')->firstOrFail();
            $transaction = WalletTransaction::create(['wallet_id' => $locked->id, 'type' => 'top_up', 'direction' => 'credit', 'amount' => $amount, 'balance_after' => $locked->balance_amount + $amount, 'idempotency_key' => $idempotencyKey, 'actor_id' => $actor?->id]);
            $entry = $this->journal->post('Partner wallet top-up', [new JournalLineInput($cash->id, $locked->currency, debitAmount: $amount), new JournalLineInput($walletAccount->id, $locked->currency, creditAmount: $amount)], actor: $actor, sourceType: WalletTransaction::class, sourceId: (string) $transaction->id);
            $transaction->forceFill(['journal_entry_id' => $entry->id])->save();
            $locked->forceFill(['balance_amount' => $transaction->balance_after])->save();

            return $transaction->refresh();
        });
    }
}
