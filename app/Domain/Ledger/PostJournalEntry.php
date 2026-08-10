<?php

namespace App\Domain\Ledger;

use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PostJournalEntry
{
    /** @param list<JournalLineInput> $lines */
    public function post(string $description, array $lines, ?CarbonImmutable $occurredAt = null, ?User $actor = null, ?string $sourceType = null, ?string $sourceId = null): JournalEntry
    {
        if ($lines === []) {
            throw new DomainException('A journal entry must contain at least two lines.');
        }

        $totals = [];
        foreach ($lines as $line) {
            $totals[$line->currency] ??= ['debit' => 0, 'credit' => 0];
            $totals[$line->currency]['debit'] += $line->debitAmount;
            $totals[$line->currency]['credit'] += $line->creditAmount;
        }
        foreach ($totals as $currency => $total) {
            if ($total['debit'] !== $total['credit']) {
                throw new DomainException("Journal entry is not balanced in {$currency}.");
            }
        }

        return DB::transaction(function () use ($description, $lines, $occurredAt, $actor, $sourceType, $sourceId): JournalEntry {
            $occurredAt ??= CarbonImmutable::now();
            $entry = JournalEntry::create([
                'public_id' => (string) Str::ulid(),
                'occurred_at' => $occurredAt,
                'description' => $description,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'actor_id' => $actor?->id,
                'posted_at' => now(),
            ]);

            foreach ($lines as $input) {
                $account = LedgerAccount::query()->findOrFail($input->accountId);
                if ($account->currency !== null && $account->currency !== $input->currency) {
                    throw new DomainException('The journal line currency does not match the account currency.');
                }

                $line = JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'customer_id' => $input->customerId,
                    'partner_id' => $input->partnerId,
                    'currency' => $input->currency,
                    'debit_amount' => $input->debitAmount,
                    'credit_amount' => $input->creditAmount,
                    'memo' => $input->memo,
                ]);

                if ($input->customerId === null) {
                    continue;
                }

                $customer = Customer::query()->lockForUpdate()->findOrFail($input->customerId);
                if ($customer->balance_currency !== $input->currency) {
                    throw new DomainException('Customer ledger currency does not match the customer balance currency.');
                }
                $previous = (int) (LedgerEntry::query()->where('customer_id', $customer->id)->latest('id')->value('balance_after') ?? 0);
                $balanceAfter = $previous + $input->debitAmount - $input->creditAmount;
                LedgerEntry::create([
                    'customer_id' => $customer->id,
                    'journal_line_id' => $line->id,
                    'currency' => $input->currency,
                    'debit_amount' => $input->debitAmount,
                    'credit_amount' => $input->creditAmount,
                    'balance_after' => $balanceAfter,
                    'occurred_at' => $occurredAt,
                ]);
                $customer->forceFill(['balance_amount' => $balanceAfter])->save();
            }

            return $entry->load('lines');
        });
    }
}
