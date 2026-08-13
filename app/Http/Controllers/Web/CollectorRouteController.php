<?php

namespace App\Http\Controllers\Web;

use App\Actions\PlanCollectorRoute;
use App\Actions\RecordCollectorRouteStop;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanCollectorRouteRequest;
use App\Http\Requests\RecordCollectorRouteStopRequest;
use App\Models\CollectorRoute;
use App\Models\CollectorRouteStop;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CollectorRoutePresenter;
use App\Support\CollectorTerritories;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CollectorRouteController extends Controller
{
    public function index(
        Request $request,
        CollectorTerritories $territories,
        CollectorRoutePresenter $presenter,
    ): Response {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can('reports.operations'), 403);
        $tenant = Tenant::query()->findOrFail($actor->tenant_id);
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date']
            ?? CarbonImmutable::now($tenant->settingsData()->timezone)->toDateString();

        $collectors = User::query()
            ->where('role', 'collector')
            ->with('activeCollectorZoneAssignments:id,user_id,zone_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'collector_all_zones'])
            ->map(function (User $collector) use ($territories): array {
                $zoneIds = $territories->zoneIds($collector);
                $customerIds = Customer::query()
                    ->when($zoneIds !== null, fn ($query) => $query->whereIn('zone_id', $zoneIds))
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all();

                return [
                    'id' => $collector->id,
                    'name' => $collector->name,
                    'email' => $collector->email,
                    'all_zones' => $zoneIds === null,
                    'customer_ids' => $customerIds,
                ];
            })->values();

        $customers = Customer::query()
            ->with('zone:id,name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'public_id', 'zone_id', 'code', 'first_name', 'last_name', 'phone', 'address', 'balance_amount', 'balance_currency'])
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'public_id' => $customer->public_id,
                'code' => $customer->code,
                'name' => $customer->full_name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'zone' => $customer->zone?->name,
                'balance_amount' => $customer->balance_amount,
                'balance_currency' => $customer->balance_currency,
            ])->values();

        $routes = CollectorRoute::query()
            ->whereDate('route_date', $date)
            ->with(['collector:id,name,email', 'stops.customer.zone', 'stops.customer.services'])
            ->orderBy('user_id')
            ->get()
            ->map(fn (CollectorRoute $route): array => $presenter->make($route, true))
            ->values();

        return Inertia::render('Operations/CollectorRoutes', [
            'date' => $date,
            'collectors' => $collectors,
            'customers' => $customers,
            'routes' => $routes,
        ]);
    }

    public function store(
        PlanCollectorRouteRequest $request,
        PlanCollectorRoute $plan,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $collector = User::query()->findOrFail($request->integer('collector_id'));

        try {
            $plan->handle($actor, $collector, (string) $request->validated('route_date'), $request->validated('customer_ids'));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['customer_ids' => $exception->getMessage()]);
        }

        return redirect()->route('operations.collector-routes', ['date' => $request->validated('route_date')])
            ->with('success', "{$collector->name}'s route was planned.");
    }

    public function recordStop(
        RecordCollectorRouteStopRequest $request,
        CollectorRouteStop $routeStop,
        RecordCollectorRouteStop $record,
        CollectorRoutePresenter $presenter,
    ): JsonResponse {
        $collector = $request->user();
        abort_unless($collector instanceof User, 401);
        $routeStop->loadMissing('route');
        abort_unless($routeStop->route->user_id === $collector->id, 404);

        try {
            $record->handle(
                $collector,
                $routeStop,
                (string) $request->validated('outcome'),
                $request->validated('note'),
                (float) $request->validated('latitude'),
                (float) $request->validated('longitude'),
                $request->validated('accuracy_meters'),
            );
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $route = CollectorRoute::query()->with(['stops.customer.zone', 'stops.customer.services'])->findOrFail($routeStop->collector_route_id);

        return response()->json(['message' => 'Visit outcome recorded.', 'data' => $presenter->make($route)]);
    }
}
