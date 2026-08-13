<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\CollectorCustodyEntry;
use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;
use App\Models\OperationalExpense;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReviewOperationalExpense implements Action
{
    public function __construct(
        private PostJournalEntry $journal,
        private GetCollectorCustodyPosition $custodyPosition,
    ) {}

    public function handle(User $reviewer, OperationalExpense $expense, string $decision, ?string $note = null): OperationalExpense
    {
        if (! $reviewer->can('expenses.approve') || (int) $reviewer->tenant_id !== (int) $expense->tenant_id) {
            throw new DomainException('You are not allowed to review this expense.');
        }
        if (! in_array($decision, ['posted', 'rejected'], true)) {
            throw new DomainException('Choose approve or reject.');
        }

        return DB::transaction(function () use ($reviewer, $expense, $decision, $note): OperationalExpense {
            $locked = OperationalExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($locked->status !== 'pending') {
                throw new DomainException('This expense has already been reviewed.');
            }

            if ($decision === 'rejected') {
                $locked->forceFill([
                    'status' => 'rejected',
                    'reviewed_by_id' => $reviewer->id,
                    'reviewed_at' => now(),
                    'review_note' => filled($note) ? trim((string) $note) : null,
                ])->save();

                return $locked->refresh();
            }

            $category = ExpenseCategory::query()->with('ledgerAccount')->findOrFail($locked->expense_category_id);
            $expenseAccount = $category->ledgerAccount;
            if (! $expenseAccount->is_active || $expenseAccount->category !== 'expense') {
                throw new DomainException('The expense category is not linked to an active expense ledger account.');
            }

            $creditAccountCode = $locked->payment_source === 'bank' ? '1010' : '1000';
            $creditAccount = LedgerAccount::query()->where('code', $creditAccountCode)->where('is_active', true)->firstOrFail();
            $custodyEntry = null;
            if ($locked->payment_source === 'collector') {
                $collector = User::query()->lockForUpdate()->findOrFail($locked->collector_id);
                $available = (int) ($this->custodyPosition->handle($collector)['balances'][$locked->currency] ?? 0);
                if ($available < $locked->amount) {
                    throw new DomainException('The expense exceeds this collector\'s available cash custody.');
                }
                $custodyEntry = CollectorCustodyEntry::create([
                    'collector_id' => $collector->id,
                    'cash_shift_id' => $locked->cash_shift_id,
                    'requested_by_id' => $locked->requested_by_id,
                    'reviewed_by_id' => $reviewer->id,
                    'type' => 'expense',
                    'direction' => 'debit',
                    'status' => 'posted',
                    'amount' => $locked->amount,
                    'currency' => $locked->currency,
                    'description' => $locked->description,
                    'reference' => $locked->reference,
                    'occurred_at' => $locked->incurred_at,
                    'reviewed_at' => now(),
                    'review_note' => filled($note) ? trim((string) $note) : null,
                ]);
            }

            $entry = $this->journal->post(
                'Operating expense '.$locked->public_id,
                [
                    new JournalLineInput($expenseAccount->id, $locked->currency, debitAmount: $locked->amount, memo: $category->name),
                    new JournalLineInput($creditAccount->id, $locked->currency, creditAmount: $locked->amount, memo: $locked->reference),
                ],
                CarbonImmutable::instance($locked->incurred_at),
                $reviewer,
                OperationalExpense::class,
                (string) $locked->id,
            );

            $locked->forceFill([
                'status' => 'posted',
                'reviewed_by_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => filled($note) ? trim((string) $note) : null,
                'journal_entry_id' => $entry->id,
                'collector_custody_entry_id' => $custodyEntry?->id,
            ])->save();

            return $locked->refresh();
        });
    }
}
