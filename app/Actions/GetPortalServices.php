<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use App\Models\Customer;
use App\Models\Service;

final readonly class GetPortalServices implements Action
{
    /** @return list<array<string, mixed>> */
    public function handle(Customer $customer): array
    {
        $services = Service::query()->where('customer_id', $customer->id)->with(['plan', 'router.pop'])->get();
        $sessions = CurrentSession::query()->whereIn('service_id', $services->modelKeys())->whereNull('stopped_at')->get()->keyBy('service_id');

        $payload = [];
        foreach ($services as $service) {
            $session = $sessions->get($service->id);

            $payload[] = [
                'uuid' => $service->public_id,
                'public_id' => $service->public_id,
                'username' => $service->username,
                'status' => $service->status->value,
                'network_state' => $service->network_state->value,
                'expires_at' => $service->expires_at?->toIso8601String(),
                'days_remaining' => $service->expires_at === null ? null : now()->diffInDays($service->expires_at, false),
                'plan' => [
                    'uuid' => $service->plan->public_id,
                    'name' => $service->plan->name,
                    'download_kbps' => $service->plan->download_kbps,
                    'upload_kbps' => $service->plan->upload_kbps,
                    'currency' => $service->plan->currency,
                ],
                'router' => $service->router === null ? null : [
                    'name' => $service->router->name,
                    'pop' => $service->router->pop?->name,
                ],
                'current_session' => $session === null ? null : [
                    'ip' => $session->framed_ip,
                    'started_at' => $session->acct_start_time?->toIso8601String(),
                    'last_seen_at' => $session->last_seen_at?->toIso8601String(),
                    'input_octets' => $session->input_octets,
                    'output_octets' => $session->output_octets,
                ],
            ];
        }

        return $payload;
    }
}
