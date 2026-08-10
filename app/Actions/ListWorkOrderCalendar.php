<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListWorkOrderCalendar implements Action
{
    /** @return Collection<int, WorkOrder> */
    public function handle(CarbonImmutable $weekStart, string $timezone): Collection
    {
        $from = $weekStart->setTimezone($timezone)->startOfDay()->utc();
        $until = $from->addDays(7);

        return WorkOrder::query()
            ->with(['customer', 'assignee'])
            ->whereBetween('scheduled_at', [$from, $until])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();
    }
}
