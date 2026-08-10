<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\NetworkState;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateService implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Customer $customer, array $data, ?User $actor = null): Service
    {
        return DB::transaction(function () use ($customer, $data, $actor): Service {
            $plan = Plan::query()->whereKey($data['plan_id'])->firstOrFail();
            if ($plan->tenant_id !== $customer->tenant_id || $plan->status !== 'active') {
                throw ValidationException::withMessages(['plan_id' => 'The selected plan is not available for this tenant.']);
            }

            $router = null;
            if (filled($data['router_id'] ?? null)) {
                $router = Router::query()->whereKey($data['router_id'])->firstOrFail();
                if ($router->tenant_id !== $customer->tenant_id) {
                    throw ValidationException::withMessages(['router_id' => 'The selected router is not available for this tenant.']);
                }
            }

            $service = Service::create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'router_id' => $router?->id,
                'username' => $data['username'],
                'password_encrypted' => $data['password'],
                'status' => ServiceStatus::Pending,
                'provisioning_mode' => $data['provisioning_mode'],
                'network_state' => NetworkState::PendingSync,
            ]);

            ServiceEvent::create([
                'service_id' => $service->id,
                'actor_id' => $actor?->id,
                'event_type' => 'created',
                'to_status' => ServiceStatus::Pending->value,
                'metadata' => ['provisioning_mode' => $service->provisioning_mode->value],
            ]);

            return $service->load('plan');
        });
    }
}
