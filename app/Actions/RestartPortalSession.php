<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Exceptions\PortalSessionRestartRateLimited;
use App\Models\CurrentSession;
use App\Models\Customer;
use App\Models\NetworkCommand;
use App\Models\Service;
use DomainException;
use Illuminate\Support\Facades\Cache;

final readonly class RestartPortalSession implements Action
{
    public function __construct(private EnqueueNetworkCommand $enqueue) {}

    public function handle(Customer $customer, Service $service): NetworkCommand
    {
        if ($service->customer_id !== $customer->id || $service->tenant_id !== $customer->tenant_id) {
            throw new DomainException('The selected service is not owned by this customer.');
        }
        if ($service->status->value === 'terminated') {
            throw new DomainException('Terminated services cannot restart a network session.');
        }

        $cacheKey = 'portal-session-restart:'.$customer->id.':'.$service->id;
        if (! Cache::add($cacheKey, true, 300)) {
            throw new PortalSessionRestartRateLimited('A session restart can be requested once every five minutes.');
        }

        try {
            $session = CurrentSession::query()
                ->where('service_id', $service->id)
                ->whereNull('stopped_at')
                ->latest('last_seen_at')
                ->first();
            $payload = array_filter([
                'reason' => 'customer_session_restart',
                'session_id' => $session?->acct_session_id,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            return $this->enqueue->handle($service, 'disconnect', $payload);
        } catch (\Throwable $exception) {
            Cache::forget($cacheKey);
            throw $exception;
        }
    }
}
