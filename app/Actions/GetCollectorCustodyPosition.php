<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\PaymentStatus;
use App\Models\CashShift;
use App\Models\CollectorCustodyEntry;
use App\Models\Payment;
use App\Models\User;

final readonly class GetCollectorCustodyPosition implements Action
{
    /** @return array<string, mixed> */
    public function handle(User $collector): array
    {
        $balances = [];
        $payments = Payment::query()
            ->where('actor_id', $collector->id)
            ->where('status', PaymentStatus::Posted)
            ->where('method', 'cash')
            ->get(['amount', 'currency']);
        foreach ($payments as $payment) {
            $balances[$payment->currency] = ($balances[$payment->currency] ?? 0) + $payment->amount;
        }

        $shifts = CashShift::query()->where('user_id', $collector->id)->get(['opening_float']);
        foreach ($shifts as $shift) {
            foreach ($shift->opening_float ?? [] as $currency => $amount) {
                $balances[$currency] = ($balances[$currency] ?? 0) + (int) $amount;
            }
        }

        $entries = CollectorCustodyEntry::query()
            ->where('collector_id', $collector->id)
            ->where('status', 'posted')
            ->get(['amount', 'currency', 'direction']);
        foreach ($entries as $entry) {
            $amount = $entry->direction === 'credit' ? $entry->amount : -$entry->amount;
            $balances[$entry->currency] = ($balances[$entry->currency] ?? 0) + $amount;
        }
        ksort($balances);

        return [
            'balances' => $balances,
            'cash_payment_count' => $payments->count(),
            'pending_count' => CollectorCustodyEntry::query()
                ->where('collector_id', $collector->id)
                ->where('status', 'pending')
                ->count(),
        ];
    }
}
