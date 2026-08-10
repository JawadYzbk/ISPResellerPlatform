<?php

namespace App\Domain\Network;

use App\Models\NetworkCommand;
use App\Models\Router;
use App\Models\Service;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

final class MikrotikApiDriver implements NetworkDriver
{
    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const READ_TIMEOUT_SECONDS = 10;

    public function execute(Service $service, NetworkCommand $command): DriverResult
    {
        $router = $service->router;
        if ($router === null) {
            return DriverResult::failure('router_not_configured');
        }

        $service->loadMissing('plan');
        $comment = $this->comment($service);
        $reference = $this->storedReference($service);

        if (in_array($command->action, ['activate', 'suspend', 'throttle'], true) && $reference === null) {
            [$reference, $lookupError] = $this->lookupReference($router, $comment);
            if ($lookupError !== null) {
                return DriverResult::failure($lookupError, ['action' => $command->action, 'router_id' => $router->id]);
            }
            if ($reference !== null) {
                $this->rememberReference($service, $reference);
            }
        }

        if ($command->action === 'disconnect') {
            return $this->disconnect($service, $router);
        }

        if (in_array($command->action, ['suspend', 'throttle'], true) && $reference === null) {
            return DriverResult::failure('router_subscriber_not_found', ['action' => $command->action, 'router_id' => $router->id]);
        }

        if (! in_array($command->action, ['activate', 'suspend', 'throttle'], true)) {
            return DriverResult::failure('unsupported_router_action: '.$command->action);
        }

        $isCreate = $command->action === 'activate' && $reference === null;
        $endpoint = $isCreate
            ? '/rest/ppp/secret/add'
            : '/rest/ppp/secret/'.rawurlencode((string) $reference);
        $payload = match ($command->action) {
            'activate' => $isCreate
                ? ['name' => $service->username, 'password' => (string) $service->password_encrypted, 'service' => 'pppoe', 'profile' => $this->profile($service), 'comment' => $comment, 'disabled' => 'no']
                : ['disabled' => 'false', 'profile' => $this->profile($service)],
            'suspend' => ['disabled' => 'yes'],
            'throttle' => ['profile' => (string) ($command->payload['fup_profile'] ?? 'fup')],
        };

        try {
            $response = $this->request($router)->{$isCreate ? 'post' : 'patch'}($router->baseUrl().$endpoint, $payload);
        } catch (ConnectionException $exception) {
            return DriverResult::failure('router_unreachable: '.$exception->getMessage());
        }

        $failure = $this->responseFailure($response);
        if ($failure !== null) {
            return DriverResult::failure($failure);
        }

        $reference ??= $this->referenceFromPayload($response->json());
        if ($reference !== null) {
            $this->rememberReference($service, $reference);
        }

        return $this->success($router, $command->action, $response, $reference);
    }

    private function request(Router $router): PendingRequest
    {
        return Http::withBasicAuth($router->username, (string) $router->password_encrypted)
            ->withOptions(['verify' => $router->tls_verify])
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::READ_TIMEOUT_SECONDS);
    }

    /** @return array{0: string|null, 1: string|null} */
    private function lookupReference(Router $router, string $comment): array
    {
        try {
            $response = $this->request($router)->get($router->baseUrl().'/rest/ppp/secret', ['comment' => $comment]);
        } catch (ConnectionException $exception) {
            return [null, 'router_unreachable: '.$exception->getMessage()];
        }

        $failure = $this->responseFailure($response);
        if ($failure !== null) {
            return [null, $failure];
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return [null, 'router_api_error: subscriber response was not a list'];
        }

        foreach ($payload as $subscriber) {
            if (! is_array($subscriber) || trim((string) ($subscriber['comment'] ?? '')) !== $comment) {
                continue;
            }

            $reference = $this->referenceFromPayload($subscriber);
            if ($reference !== null) {
                return [$reference, null];
            }
        }

        return [null, null];
    }

    private function disconnect(Service $service, Router $router): DriverResult
    {
        try {
            $response = $this->request($router)->get($router->baseUrl().'/rest/ppp/active', ['name' => $service->username]);
        } catch (ConnectionException $exception) {
            return DriverResult::failure('router_unreachable: '.$exception->getMessage());
        }

        $failure = $this->responseFailure($response);
        if ($failure !== null) {
            return DriverResult::failure($failure);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return DriverResult::failure('router_api_error: active session response was not a list');
        }

        $disconnected = 0;
        foreach ($payload as $session) {
            if (! is_array($session)) {
                continue;
            }

            $sessionId = $this->referenceFromPayload($session);
            if ($sessionId === null) {
                continue;
            }

            try {
                $deleteResponse = $this->request($router)->delete($router->baseUrl().'/rest/ppp/active/'.rawurlencode($sessionId));
            } catch (ConnectionException $exception) {
                return DriverResult::failure('router_unreachable: '.$exception->getMessage());
            }

            $failure = $this->responseFailure($deleteResponse);
            if ($failure !== null) {
                return DriverResult::failure($failure);
            }

            $disconnected++;
        }

        return DriverResult::success('RouterOS sessions disconnected.', [
            'router_id' => $router->id,
            'action' => 'disconnect',
            'http_status' => $response->status(),
            'disconnected_sessions' => $disconnected,
        ]);
    }

    private function responseFailure(Response $response): ?string
    {
        if ($response->status() === 401 || $response->status() === 403) {
            return 'router_credentials_rejected';
        }
        if ($response->failed()) {
            return 'router_api_error: HTTP '.$response->status();
        }

        return null;
    }

    private function comment(Service $service): string
    {
        return 'svc:'.$service->public_id;
    }

    private function profile(Service $service): string
    {
        $configured = $service->plan?->metadata['routeros_profile'] ?? null;

        return is_string($configured) && trim($configured) !== '' ? trim($configured) : 'plan-'.$service->plan->slug;
    }

    private function storedReference(Service $service): ?string
    {
        $reference = Arr::get($service->metadata ?? [], 'routeros_id');

        return is_scalar($reference) && trim((string) $reference) !== '' ? trim((string) $reference) : null;
    }

    private function rememberReference(Service $service, string $reference): void
    {
        $metadata = $service->metadata ?? [];
        $service->forceFill(['metadata' => [...$metadata, 'routeros_id' => $reference]])->save();
    }

    private function referenceFromPayload(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach (['.id', 'ret', 'id'] as $key) {
            $reference = $payload[$key] ?? null;
            if (is_scalar($reference) && trim((string) $reference) !== '') {
                return trim((string) $reference);
            }
        }

        return null;
    }

    private function success(Router $router, string $action, Response $response, ?string $reference): DriverResult
    {
        return DriverResult::success('RouterOS command accepted.', array_filter([
            'router_id' => $router->id,
            'action' => $action,
            'http_status' => $response->status(),
            'routeros_id' => $reference,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
