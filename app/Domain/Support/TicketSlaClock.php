<?php

namespace App\Domain\Support;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class TicketSlaClock
{
    /** @var array<string, int> */
    private const SLA_MINUTES = ['urgent' => 240, 'high' => 480, 'normal' => 1440, 'low' => 4320];

    public function dueAt(Tenant $tenant, string $priority, CarbonImmutable $openedAt): CarbonImmutable
    {
        $remaining = self::SLA_MINUTES[$priority] ?? throw new InvalidArgumentException("Unknown ticket priority [{$priority}].");
        $cursor = $openedAt->setTimezone($tenant->timezone);

        while ($remaining > 0) {
            if ($cursor->isWeekend()) {
                $cursor = $this->nextWorkday($cursor);

                continue;
            }
            if ($cursor->hour < 9) {
                $cursor = $cursor->setTime(9, 0);
            }
            if ($cursor->hour >= 18) {
                $cursor = $this->nextWorkday($cursor);

                continue;
            }

            $endOfWindow = $cursor->setTime(18, 0);
            $available = $cursor->diffInMinutes($endOfWindow);
            if ($remaining <= $available) {
                return $cursor->addMinutes($remaining)->setTimezone($openedAt->getTimezone());
            }
            $remaining -= $available;
            $cursor = $this->nextWorkday($cursor);
        }

        return $cursor->setTimezone($openedAt->getTimezone());
    }

    private function nextWorkday(CarbonImmutable $cursor): CarbonImmutable
    {
        do {
            $cursor = $cursor->addDay()->setTime(9, 0);
        } while ($cursor->isWeekend());

        return $cursor;
    }
}
