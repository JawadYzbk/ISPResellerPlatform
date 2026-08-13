<?php

namespace App\Domain\Billing;

use App\Data\BillingCycleQuote;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class AnchoredBillingCycle
{
    public function __construct(public int $anchorDay)
    {
        if ($anchorDay < 1 || $anchorDay > 31) {
            throw new InvalidArgumentException('A billing anchor day must be between 1 and 31.');
        }
    }

    public function nextAnchorAfter(CarbonImmutable $after): CarbonImmutable
    {
        $candidate = $this->anchorInMonth($after);

        return $candidate->greaterThan($after)
            ? $candidate
            : $this->anchorInMonth($after->startOfMonth()->addMonthNoOverflow());
    }

    public function quote(CarbonImmutable $startsAt, int $fullAmount, string $currency): BillingCycleQuote
    {
        if ($fullAmount < 0) {
            throw new InvalidArgumentException('The full billing amount cannot be negative.');
        }

        $endsAt = $this->nextAnchorAfter($startsAt);
        $cycleStartsAt = $this->anchorInMonth($endsAt->startOfMonth()->subMonthNoOverflow());
        $billableDays = max(1, $startsAt->startOfDay()->diffInDays($endsAt->startOfDay()));
        $cycleDays = max(1, $cycleStartsAt->startOfDay()->diffInDays($endsAt->startOfDay()));
        $proratedAmount = intdiv(($fullAmount * $billableDays) + intdiv($cycleDays, 2), $cycleDays);

        return new BillingCycleQuote(
            $this->anchorDay,
            $startsAt,
            $endsAt,
            $billableDays,
            $cycleDays,
            $fullAmount,
            $proratedAmount,
            strtoupper($currency),
        );
    }

    private function anchorInMonth(CarbonImmutable $month): CarbonImmutable
    {
        return $month
            ->startOfMonth()
            ->setDay(min($this->anchorDay, $month->daysInMonth))
            ->endOfDay()
            ->setMicrosecond(0);
    }
}
