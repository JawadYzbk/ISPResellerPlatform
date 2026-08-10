<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CommissionEntry;
use App\Models\Partner;
use App\Models\PartnerWallet;
use App\Models\Settlement;
use App\Models\WalletTransaction;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class GeneratePartnerSettlement implements Action
{
    public function handle(Partner $partner, CarbonInterface $periodStart, CarbonInterface $periodEnd, string $currency): Settlement
    {
        if ($partner->tenant_id !== app(Tenancy::class)->requireId()) {
            throw new DomainException('Partner must belong to the current tenant.');
        }
        if ($periodEnd->lessThan($periodStart)) {
            throw new DomainException('Settlement period end must not precede its start.');
        }

        $start = $periodStart->copy()->startOfDay();
        $end = $periodEnd->copy()->endOfDay();
        $currency = strtoupper($currency);
        $existing = Settlement::query()
            ->where('partner_id', $partner->id)
            ->whereDate('period_start', $start)
            ->whereDate('period_end', $end)
            ->where('currency', $currency)
            ->first();
        if ($existing instanceof Settlement) {
            return $existing;
        }

        return DB::transaction(function () use ($partner, $start, $end, $currency): Settlement {
            $existing = Settlement::query()
                ->where('partner_id', $partner->id)
                ->whereDate('period_start', $start)
                ->whereDate('period_end', $end)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof Settlement) {
                return $existing;
            }

            $wallet = PartnerWallet::query()
                ->where('partner_id', $partner->id)
                ->where('currency', $currency)
                ->firstOrFail();
            $opening = (int) (WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('created_at', '<', $start)
                ->latest('id')
                ->value('balance_after') ?? 0);
            $activity = (int) WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->whereBetween('created_at', [$start, $end])
                ->get(['direction', 'amount'])
                ->sum(fn ($transaction): int => $transaction->direction === 'credit' ? $transaction->amount : -$transaction->amount);
            $due = (int) CommissionEntry::query()
                ->where('partner_id', $partner->id)
                ->where('currency', $currency)
                ->where('status', 'accrued')
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount_minor');

            return Settlement::create([
                'partner_id' => $partner->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'currency' => $currency,
                'opening_amount' => $opening,
                'activity_amount' => $activity,
                'closing_amount' => $opening + $activity,
                'due_amount' => $due,
                'status' => 'draft',
            ]);
        });
    }
}
