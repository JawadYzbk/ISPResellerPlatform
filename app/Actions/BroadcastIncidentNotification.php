<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\CustomerStatus;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class BroadcastIncidentNotification implements Action
{
    public function __construct(private QueueCustomerNotification $queueNotification) {}

    public function handle(Incident $incident, string $templateKey): int
    {
        $queued = 0;
        foreach ($this->customers($incident) as $customer) {
            $message = $this->queueNotification->handle(
                $customer,
                $templateKey,
                sprintf('incident-broadcast:%d:%s:%d', $incident->id, $templateKey, $customer->id),
                [
                    'customer_name' => trim($customer->first_name.' '.$customer->last_name),
                    'incident_title' => $incident->title,
                    'incident_description' => $incident->description ?? '',
                    'severity' => $incident->severity,
                ],
            );

            $queued += $message === null ? 0 : 1;
        }

        return $queued;
    }

    /** @return Collection<int, Customer> */
    private function customers(Incident $incident): Collection
    {
        $metadata = $incident->metadata ?? [];
        $popId = is_numeric($metadata['pop_id'] ?? null) ? (int) $metadata['pop_id'] : null;
        $zoneId = is_numeric($metadata['zone_id'] ?? null) ? (int) $metadata['zone_id'] : null;

        if ($incident->service_id === null && $incident->router_id === null && $popId === null && $zoneId === null) {
            return new Collection;
        }

        return Customer::query()
            ->where('status', CustomerStatus::Active)
            ->where(function (Builder $query) use ($incident, $popId, $zoneId): void {
                if ($incident->service_id !== null) {
                    $query->orWhereHas('services', fn (Builder $services): Builder => $services
                        ->whereKey($incident->service_id)
                        ->where('status', ServiceStatus::Active));
                }
                if ($incident->router_id !== null) {
                    $query->orWhereHas('services', fn (Builder $services): Builder => $services
                        ->where('router_id', $incident->router_id)
                        ->where('status', ServiceStatus::Active));
                }
                if ($popId !== null) {
                    $query->orWhereHas('services.router', fn (Builder $routers): Builder => $routers
                        ->whereKey($popId)
                        ->where('status', 'active'));
                }
                if ($zoneId !== null) {
                    $query->orWhere('zone_id', $zoneId);
                }
            })
            ->get();
    }
}
