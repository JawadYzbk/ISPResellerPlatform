<?php

namespace App\Actions;

use App\Contracts\Action;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class MonitorPlatform implements Action
{
    private const STATE_KEY = 'platform:monitor:last-status';

    public function __construct(private CheckApplicationHealth $health) {}

    /** @return array{status: string, checks: array<string, string|int>, alert: string} */
    public function handle(): array
    {
        $health = $this->health->handle();
        $status = $health['status'];

        if (! (bool) config('monitoring.enabled')) {
            return [...$health, 'alert' => 'disabled'];
        }

        $signals = $this->degradedSignals($health['checks']);
        $signature = hash('sha256', json_encode($signals, JSON_THROW_ON_ERROR));
        $previous = Cache::get(self::STATE_KEY);
        $previousState = is_array($previous) && is_string($previous['status'] ?? null) && is_string($previous['signature'] ?? null)
            ? ['status' => $previous['status'], 'signature' => $previous['signature']]
            : (is_string($previous) && in_array($previous, ['ok', 'degraded'], true)
                ? ['status' => $previous, 'signature' => $previous === $status ? $signature : null]
                : null);

        if ($previousState !== null && $previousState['status'] === $status && $previousState['signature'] === $signature) {
            return [...$health, 'alert' => 'suppressed'];
        }

        if ($status === 'ok' && $previousState === null) {
            Cache::put(self::STATE_KEY, ['status' => $status, 'signature' => $signature], now()->addDay());

            return [...$health, 'alert' => 'baseline'];
        }

        $event = $status === 'ok' && ($previousState['status'] ?? null) === 'degraded' ? 'recovered' : 'degraded';
        $payload = [
            'event' => $event,
            'status' => $status,
            'checks' => $health['checks'],
            'signals' => $signals,
            'environment' => (string) config('app.env'),
            'release' => (string) config('app.version', 'unknown'),
            'observed_at' => now()->toIso8601String(),
        ];
        $this->deliver($payload);
        Cache::put(self::STATE_KEY, ['status' => $status, 'signature' => $signature], now()->addDay());

        return [...$health, 'alert' => 'sent'];
    }

    /** @param array<string, string|int> $checks @return array<string, string|int> */
    private function degradedSignals(array $checks): array
    {
        $signals = [];
        foreach ($checks as $key => $value) {
            if (in_array($value, ['failed', 'pending', 'stale', 'degraded'], true) || ($key === 'router_incidents' && is_int($value) && $value > 0)) {
                $signals[$key] = $value;
            }
        }

        ksort($signals);

        return $signals;
    }

    /** @param array<string, mixed> $payload */
    private function deliver(array $payload): void
    {
        $url = config('monitoring.webhook_url');
        $secret = config('monitoring.webhook_secret');
        if (! is_string($url) || trim($url) === '' || ! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Monitoring alert routing is not configured.');
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $response = Http::withHeaders([
            'X-Platform-Alert-Signature' => hash_hmac('sha256', $body, $secret),
            'User-Agent' => 'ISP-Manager-Monitor/1.0',
        ])
            ->timeout((int) config('monitoring.timeout', 10))
            ->withBody($body, 'application/json')
            ->post($url);

        if ($response->failed()) {
            throw new RuntimeException('Monitoring alert delivery failed.');
        }
    }
}
