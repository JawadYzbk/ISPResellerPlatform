<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;

final readonly class GetCollectorSummary implements Action
{
    /** @return array<string, mixed> */
    public function handle(User $user, string $date): array
    {
        $payments = Payment::query()
            ->where('actor_id', $user->id)
            ->where('status', PaymentStatus::Posted)
            ->whereDate('received_at', $date)
            ->get(['amount', 'currency']);
        $totals = [];
        foreach ($payments as $payment) {
            $totals[$payment->currency] = ($totals[$payment->currency] ?? 0) + $payment->amount;
        }
        ksort($totals);

        return ['date' => $date, 'payment_count' => $payments->count(), 'totals' => $totals];
    }
}
