<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\IncidentStatus;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;

final readonly class GetPortalNotices implements Action
{
    /** @return list<array<string, mixed>> */
    public function handle(Customer $customer): array
    {
        $services = Service::query()->where('customer_id', $customer->id)->with('router')->get();
        $serviceIds = $services->modelKeys();
        $routerIds = $services->pluck('router_id')->filter()->values()->all();
        $popIds = $services->pluck('router')->filter()->pluck('pop_id')->filter()->values()->all();
        $zoneId = $customer->zone_id;

        $incidents = Incident::query()
            ->where('status', IncidentStatus::Open)
            ->where(function (Builder $query) use ($serviceIds, $routerIds, $popIds, $zoneId): void {
                if ($serviceIds !== []) {
                    $query->orWhereIn('service_id', $serviceIds);
                }
                if ($routerIds !== []) {
                    $query->orWhereIn('router_id', $routerIds);
                }
                foreach ($popIds as $popId) {
                    $query->orWhereJsonContains('metadata->pop_id', $popId);
                }
                if ($zoneId !== null) {
                    $query->orWhereJsonContains('metadata->zone_id', $zoneId);
                }
            })
            ->latest('opened_at')
            ->limit(50)
            ->get();
        $payload = [];
        foreach ($incidents as $incident) {
            $payload[] = [
                'uuid' => $incident->public_id,
                'type' => $incident->type,
                'severity' => $incident->severity,
                'title' => $incident->title,
                'description' => $incident->description,
                'opened_at' => $incident->opened_at?->toIso8601String(),
            ];
        }

        return $payload;
    }
}
