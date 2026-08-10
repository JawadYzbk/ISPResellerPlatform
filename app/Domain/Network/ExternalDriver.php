<?php

namespace App\Domain\Network;

use App\Models\NetworkCommand;
use App\Models\Service;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class ExternalDriver implements NetworkDriver
{
    /**
     * External adapters receive only the stable service identity and an allowlisted
     * command payload. Credentials and arbitrary command data never leave the app.
     */
    public function execute(Service $service, NetworkCommand $command): DriverResult
    {
        $endpoint = config('services.external_network.endpoint');
        if (! is_string($endpoint) || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return DriverResult::failure('external_endpoint_not_configured');
        }

        $request = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.external_network.timeout', 5));
        $token = config('services.external_network.token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post($endpoint, [
                'command_id' => $command->public_id,
                'action' => $command->action,
                'desired_state_version' => $command->desired_state_version,
                'service' => [
                    'public_id' => $service->public_id,
                    'username' => $service->username,
                    'plan_id' => $service->plan_id,
                ],
                'payload' => $this->allowlistedPayload($command->payload ?? []),
            ]);
        } catch (ConnectionException) {
            return DriverResult::failure('external_service_unreachable');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return DriverResult::failure('external_credentials_rejected');
        }
        if ($response->failed()) {
            return DriverResult::failure('external_api_error: HTTP '.$response->status());
        }

        return DriverResult::success('External network command accepted.', [
            'action' => $command->action,
            'command_id' => $command->public_id,
            'response' => is_array($response->json()) ? $response->json() : [],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function allowlistedPayload(array $payload): array
    {
        return array_intersect_key($payload, array_flip(['fup_profile', 'session_id', 'reason']));
    }
}
