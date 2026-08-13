<?php

namespace App\Support\Api;

use App\Models\Service;
use App\Models\ServiceEvent;

final class ServiceApiResource
{
    /** @return array<string, mixed> */
    public function make(Service $service): array
    {
        $service->loadMissing(['customer', 'plan', 'router.pop', 'events']);

        return [
            'id' => $service->public_id,
            'username' => $service->username,
            'status' => $service->status->value,
            'network_state' => $service->network_state->value,
            'provisioning_mode' => $service->provisioning_mode->value,
            'expires_at' => $service->expires_at?->toIso8601String(),
            'billing_anchor_day' => $service->billing_anchor_day,
            'activated_at' => $service->activated_at?->toIso8601String(),
            'suspension_reason' => $service->suspension_reason,
            'paused_until' => $service->paused_until?->toIso8601String(),
            'current_period_bytes' => $service->current_period_bytes,
            'fup_applied_at' => $service->fup_applied_at?->toIso8601String(),
            'pending_billing_cycle' => is_array(($service->metadata ?? [])['pending_billing_cycle'] ?? null)
                ? ($service->metadata ?? [])['pending_billing_cycle']
                : null,
            'customer' => $service->customer === null ? null : [
                'id' => $service->customer->public_id,
                'code' => $service->customer->code,
                'name' => $service->customer->full_name,
            ],
            'plan' => $service->plan === null ? null : [
                'id' => $service->plan->public_id,
                'name' => $service->plan->name,
                'download_kbps' => $service->plan->download_kbps,
                'upload_kbps' => $service->plan->upload_kbps,
                'duration_days' => $service->plan->duration_days,
                'currency' => $service->plan->currency,
            ],
            'router' => $service->router === null ? null : [
                'id' => $service->router->public_id,
                'name' => $service->router->name,
                'status' => $service->router->status,
                'pop' => $service->router->pop === null ? null : [
                    'name' => $service->router->pop->name,
                    'code' => $service->router->pop->code,
                ],
            ],
            'events' => $service->events->map(fn (ServiceEvent $event): array => [
                'type' => $event->event_type,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'metadata' => $event->metadata ?? [],
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
