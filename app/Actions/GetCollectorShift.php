<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CashShift;
use App\Models\User;

final readonly class GetCollectorShift implements Action
{
    /** @return array<string, mixed>|null */
    public function handle(User $user): ?array
    {
        $shift = CashShift::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
        if (! $shift instanceof CashShift) {
            return null;
        }

        $systemTotals = array_map('intval', $shift->opening_float ?? []);
        foreach ($shift->payments()->where('status', 'posted')->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency') as $currency => $total) {
            $systemTotals[$currency] = ($systemTotals[$currency] ?? 0) + (int) $total;
        }
        ksort($systemTotals);

        return [
            'id' => $shift->public_id,
            'status' => $shift->status->value,
            'opened_at' => $shift->opened_at?->toIso8601String(),
            'opening_float' => $shift->opening_float ?? [],
            'system_totals' => $systemTotals,
            'payment_count' => $shift->payments()->where('status', 'posted')->count(),
        ];
    }
}
