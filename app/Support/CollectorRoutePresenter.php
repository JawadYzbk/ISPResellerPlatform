<?php

namespace App\Support;

use App\Models\CollectorRoute;
use App\Models\CollectorRouteStop;
use App\Models\Service;

final class CollectorRoutePresenter
{
    /** @return array<string, mixed> */
    public function make(CollectorRoute $route, bool $includeCollector = false): array
    {
        $route->loadMissing(['collector:id,name,email', 'stops.customer.zone', 'stops.customer.services']);
        $stops = $route->stops->map(fn (CollectorRouteStop $stop): array => $this->stop($stop))->values();

        return [
            'id' => $route->public_id,
            'route_date' => $route->route_date->toDateString(),
            'status' => $route->status,
            'started_at' => $route->started_at?->toIso8601String(),
            'completed_at' => $route->completed_at?->toIso8601String(),
            'collector' => $includeCollector ? [
                'id' => $route->collector->id,
                'name' => $route->collector->name,
                'email' => $route->collector->email,
            ] : null,
            'stop_count' => $stops->count(),
            'completed_count' => $stops->where('outcome', '!=', 'pending')->count(),
            'stops' => $stops,
        ];
    }

    /** @return array<string, mixed> */
    private function stop(CollectorRouteStop $stop): array
    {
        $customer = $stop->customer;
        $nextExpiry = $customer->services
            ->filter(fn (Service $service): bool => $service->status->value !== 'terminated' && $service->expires_at !== null)
            ->sortBy('expires_at')
            ->first()?->expires_at;

        return [
            'id' => $stop->public_id,
            'position' => $stop->position,
            'outcome' => $stop->outcome,
            'note' => $stop->note,
            'visited_at' => $stop->visited_at?->toIso8601String(),
            'customer' => [
                'id' => $customer->public_id,
                'code' => $customer->code,
                'name' => $customer->full_name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'latitude' => $customer->latitude === null ? null : (float) $customer->latitude,
                'longitude' => $customer->longitude === null ? null : (float) $customer->longitude,
                'zone' => $customer->zone?->name,
                'balance_amount' => $customer->balance_amount,
                'balance_currency' => $customer->balance_currency,
                'next_expires_at' => $nextExpiry?->toIso8601String(),
            ],
        ];
    }
}
