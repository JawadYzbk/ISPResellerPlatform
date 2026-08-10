<?php

namespace App\Domain\Network;

use App\Models\NetworkCommand;
use App\Models\Service;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class MikrotikApiDriver implements NetworkDriver
{
    public function execute(Service $service, NetworkCommand $command): DriverResult
    {
        $router = $service->router;
        if ($router === null) {
            return DriverResult::failure('router_not_configured');
        }

        $service->loadMissing('plan');
        $endpoint = match ($command->action) {
            'activate' => '/rest/ppp/secret/add',
            'suspend' => '/rest/ppp/secret/set',
            'throttle' => '/rest/ppp/secret/set',
            'disconnect' => '/rest/ppp/active/remove',
            default => null,
        };
        if ($endpoint === null) {
            return DriverResult::failure('unsupported_router_action: '.$command->action);
        }

        $payload = match ($command->action) {
            'activate' => ['name' => $service->username, 'password' => (string) $service->password_encrypted, 'profile' => 'plan-'.$service->plan_id, 'disabled' => 'no'],
            'suspend' => ['numbers' => $service->username, 'disabled' => 'yes'],
            'throttle' => ['numbers' => $service->username, 'profile' => (string) ($command->payload['fup_profile'] ?? 'fup')],
            'disconnect' => ['numbers' => $service->username],
        };

        try {
            $response = Http::withBasicAuth($router->username, (string) $router->password_encrypted)
                ->withOptions(['verify' => $router->tls_verify])
                ->timeout(5)
                ->post($router->baseUrl().$endpoint, $payload);
        } catch (ConnectionException $exception) {
            return DriverResult::failure('router_unreachable: '.$exception->getMessage());
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return DriverResult::failure('router_credentials_rejected');
        }
        if ($response->failed()) {
            return DriverResult::failure('router_api_error: HTTP '.$response->status());
        }

        return DriverResult::success('RouterOS command accepted.', ['router_id' => $router->id, 'action' => $command->action, 'response' => $response->json()]);
    }
}
