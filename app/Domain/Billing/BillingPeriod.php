<?php

namespace App\Domain\Billing;

use App\Enums\BillingCadence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class BillingPeriod
{
    private function __construct(
        public CarbonImmutable $anchor,
        public BillingCadence $cadence,
        public int $customDays = 0,
    ) {
        if ($cadence === BillingCadence::Custom && $customDays < 1) {
            throw new InvalidArgumentException('Custom billing periods require at least one day.');
        }
    }

    public static function monthly(CarbonImmutable $anchor): self
    {
        return new self($anchor, BillingCadence::Monthly);
    }

    public static function weekly(CarbonImmutable $anchor): self
    {
        return new self($anchor, BillingCadence::Weekly);
    }

    public static function custom(CarbonImmutable $anchor, int $days): self
    {
        return new self($anchor, BillingCadence::Custom, $days);
    }

    public function renewFrom(CarbonImmutable $now, ?CarbonImmutable $expiresAt = null, bool $graceExtendsPeriod = false): CarbonImmutable
    {
        $base = match (true) {
            $expiresAt === null => $now,
            $expiresAt->greaterThanOrEqualTo($now) => $expiresAt,
            $graceExtendsPeriod => $expiresAt,
            default => $now,
        };

        return match ($this->cadence) {
            BillingCadence::Monthly => $this->nextMonthly($base),
            BillingCadence::Weekly => $base->addWeek(),
            BillingCadence::Custom => $base->addDays($this->customDays),
        };
    }

    private function nextMonthly(CarbonImmutable $base): CarbonImmutable
    {
        $nextMonth = $base->startOfMonth()->addMonthNoOverflow();
        $day = min($this->anchor->day, $nextMonth->daysInMonth);

        return $nextMonth->setDay($day)->setTime($base->hour, $base->minute, $base->second);
    }
}
