<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorRoute;
use App\Models\CollectorRouteStop;
use App\Models\Customer;
use App\Models\User;
use App\Support\CollectorTerritories;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class PlanCollectorRoute implements Action
{
    public function __construct(private CollectorTerritories $territories) {}

    /** @param list<int> $customerIds */
    public function handle(User $actor, User $collector, string $date, array $customerIds): CollectorRoute
    {
        if ($actor->tenant_id === null || $actor->tenant_id !== $collector->tenant_id || ! $actor->can('reports.operations')) {
            throw new DomainException('You are not allowed to plan this collector route.');
        }
        if ($collector->role !== 'collector') {
            throw new DomainException('Routes can only be assigned to collector accounts.');
        }
        $routeDate = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        if (! $routeDate instanceof CarbonImmutable) {
            throw new DomainException('Choose a valid route date.');
        }

        $customerIds = array_values(array_unique(array_map('intval', $customerIds)));
        if ($customerIds === []) {
            throw new DomainException('Choose at least one customer stop.');
        }

        $customers = Customer::query()->whereIn('id', $customerIds)->get()->keyBy('id');
        if ($customers->count() !== count($customerIds)) {
            throw new DomainException('One or more customer stops are not available in this workspace.');
        }
        foreach ($customers as $customer) {
            if (! $this->territories->allowsCustomer($collector, $customer)) {
                throw new DomainException("{$customer->full_name} is outside this collector's territory.");
            }
        }

        return DB::transaction(function () use ($actor, $collector, $routeDate, $customerIds): CollectorRoute {
            User::query()->lockForUpdate()->findOrFail($collector->id);
            $route = CollectorRoute::query()
                ->where('user_id', $collector->id)
                ->whereDate('route_date', $routeDate->toDateString())
                ->lockForUpdate()
                ->first();
            if ($route instanceof CollectorRoute && $route->status !== 'planned') {
                throw new DomainException('A route that has started cannot be replanned.');
            }

            $route ??= CollectorRoute::create([
                'user_id' => $collector->id,
                'planned_by_id' => $actor->id,
                'route_date' => $routeDate,
                'status' => 'planned',
            ]);
            $route->forceFill(['planned_by_id' => $actor->id])->save();
            $route->stops()->delete();

            foreach ($customerIds as $position => $customerId) {
                CollectorRouteStop::create([
                    'collector_route_id' => $route->id,
                    'customer_id' => $customerId,
                    'position' => $position + 1,
                    'outcome' => 'pending',
                ]);
            }

            return $route->refresh()->load(['collector', 'stops.customer.zone']);
        });
    }
}
