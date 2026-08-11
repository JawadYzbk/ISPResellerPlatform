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

        $previous = Cache::get(self::STATE_KEY);
        if ($previous === $status) {
            return [...$health, 'alert' => 'suppressed'];
        }

        if ($status === 'ok' && $previous === null) {
            Cache::put(self::STATE_KEY, $status, now()->addDay());

            return [...$health, 'alert' => 'baseline'];
        }

        $event = $status === 'ok' && $previous === 'degraded' ? 'recovered' : 'degraded';
        $payload = [
            'event' => $event,
            'status' => $status,
            'checks' => $health['checks'],
            'environment' => (string) config('app.env'),
            'release' => (string) config('app.version', 'unknown'),
            'observed_at' => now()->toIso8601String(),
        ];
        $this->deliver($payload);
        Cache::put(self::STATE_KEY, $status, now()->addDay());

        return [...$health, 'alert' => 'sent'];
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
