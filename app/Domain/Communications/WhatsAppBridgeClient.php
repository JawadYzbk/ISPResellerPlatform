<?php

namespace App\Domain\Communications;

use App\Models\WhatsAppAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class WhatsAppBridgeClient
{
    /** @return array<string, mixed> */
    public function ensure(WhatsAppAccount $account): array
    {
        return $this->request('post', '/accounts', ['account_id' => $account->bridge_id]);
    }

    /** @return array<string, mixed> */
    public function status(WhatsAppAccount $account): array
    {
        return $this->request('get', '/accounts/'.rawurlencode($account->bridge_id).'/status', timeout: 2);
    }

    /** @return array<string, mixed> */
    public function disconnect(WhatsAppAccount $account): array
    {
        return $this->request('post', '/accounts/'.rawurlencode($account->bridge_id).'/disconnect', ['restart' => true]);
    }

    /** @return array<string, mixed> */
    public function send(WhatsAppAccount $account, string $idempotencyKey, string $recipient, string $body): array
    {
        return $this->request('post', '/accounts/'.rawurlencode($account->bridge_id).'/messages', [
            'idempotency_key' => $idempotencyKey,
            'to' => $recipient,
            'body' => $body,
        ]);
    }

    public function configured(): bool
    {
        return (string) config('services.whatsapp.mode') === 'web'
            && (bool) config('services.whatsapp.web.enabled')
            && $this->valueConfigured(config('services.whatsapp.web.endpoint'))
            && $this->valueConfigured(config('services.whatsapp.web.token'));
    }

    /** @param array<string, mixed>|null $payload @return array<string, mixed> */
    private function request(string $method, string $path, ?array $payload = null, int $timeout = 15): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('WhatsApp Web.js is not configured.');
        }

        try {
            $request = Http::withToken((string) config('services.whatsapp.web.token'))
                ->acceptJson()
                ->timeout($timeout);
            $response = $method === 'get'
                ? $request->get($this->url($path))
                : $request->post($this->url($path), $payload ?? []);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The private WhatsApp bridge is unreachable.', previous: $exception);
        }

        if ($response->failed()) {
            $message = $response->json('error');
            throw new RuntimeException(is_string($message) ? $message : 'The private WhatsApp bridge rejected the request.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('The private WhatsApp bridge returned an invalid response.');
        }

        return $data;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.whatsapp.web.endpoint'), '/').$path;
    }

    private function valueConfigured(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
