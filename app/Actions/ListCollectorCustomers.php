<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\CursorPaginator;

final readonly class ListCollectorCustomers implements Action
{
    /** @param list<string> $statuses @return CursorPaginator<int, Customer> */
    public function handle(User $user, ?string $search = null, ?string $zone = null, array $statuses = [], int $perPage = 50): CursorPaginator
    {
        $now = CarbonImmutable::now();
        $dueUntil = $now->addDays(7);
        $query = Customer::query()
            ->with(['zone', 'services.plan'])
            ->when($search !== null, fn ($query) => $query->search($search))
            ->when($zone !== null, fn ($query) => $query->whereHas('zone', fn ($zoneQuery) => $zoneQuery->where('code', $zone)))
            ->when($statuses !== [], function ($query) use ($statuses, $now, $dueUntil): void {
                $query->where(function ($statusQuery) use ($statuses, $now, $dueUntil): void {
                    if (in_array('due', $statuses, true)) {
                        $statusQuery->whereHas('services', fn ($serviceQuery) => $serviceQuery->where('status', '!=', 'terminated')->whereBetween('expires_at', [$now, $dueUntil]));
                    }
                    if (in_array('overdue', $statuses, true)) {
                        $statusQuery->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('status', '!=', 'terminated')->whereNotNull('expires_at')->where('expires_at', '<', $now));
                    }
                });
            })
            ->orderBy('id');

        return $query->cursorPaginate(min(max($perPage, 1), 100));
    }
}
