<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Support\QrCodeRenderer;
use Illuminate\Support\Facades\Http;

final readonly class GetWhatsAppSetupStatus implements Action
{
    public function __construct(private QrCodeRenderer $qrCode) {}

    /** @return array{mode: string, enabled: bool, configured: bool, status: string, detail: string|null, qr_code: string|null, webhook_configured: bool} */
    public function handle(bool $probeBridge = true): array
    {
        $mode = (string) config('services.whatsapp.mode', 'cloud');

        if ($mode !== 'web') {
            $configured = $this->configured(config('services.whatsapp.token'))
                && $this->configured(config('services.whatsapp.phone_number_id'));

            return [
                'mode' => 'cloud',
                'enabled' => $configured,
                'configured' => $configured,
                'status' => $configured ? 'configured' : 'not_configured',
                'detail' => $configured ? 'WhatsApp Cloud API credentials are present.' : 'Cloud API credentials are missing.',
                'qr_code' => null,
                'webhook_configured' => $this->configured(config('services.webhooks.secrets.whatsapp')),
            ];
        }

        $enabled = (bool) config('services.whatsapp.web.enabled', false);
        $webhookConfigured = $this->configured(config('services.whatsapp.web.webhook_url'))
            && $this->configured(config('services.webhooks.secrets.whatsapp_web'));
        $configured = $enabled
            && $this->configured(config('services.whatsapp.web.endpoint'))
            && $this->configured(config('services.whatsapp.web.token'))
            && $webhookConfigured;

        if (! $configured) {
            return [
                'mode' => 'web',
                'enabled' => $enabled,
                'configured' => false,
                'status' => $enabled ? 'not_configured' : 'disabled',
                'detail' => $enabled ? 'The Web.js bridge still needs its endpoint, token, and signed webhook settings.' : 'WhatsApp Web.js is disabled.',
                'qr_code' => null,
                'webhook_configured' => $webhookConfigured,
            ];
        }

        if (! $probeBridge) {
            return [
                'mode' => 'web',
                'enabled' => true,
                'configured' => true,
                'status' => 'configured',
                'detail' => 'Bridge configuration is present. Open WhatsApp setup to check pairing status.',
                'qr_code' => null,
                'webhook_configured' => true,
            ];
        }

        try {
            $response = Http::withToken((string) config('services.whatsapp.web.token'))
                ->acceptJson()
                ->timeout(2)
                ->get(rtrim((string) config('services.whatsapp.web.endpoint'), '/').'/status');
            $payload = $response->json();
            $status = is_array($payload) && is_string($payload['status'] ?? null) ? $payload['status'] : 'unknown';
            $qr = $status === 'qr' && is_string($payload['qr'] ?? null) ? $payload['qr'] : null;

            return [
                'mode' => 'web',
                'enabled' => true,
                'configured' => $response->successful(),
                'status' => $response->successful() ? $status : 'unreachable',
                'detail' => $response->successful() ? null : 'The private bridge did not return a healthy status response.',
                'qr_code' => $qr === null ? null : $this->qrCode->dataUri($qr),
                'webhook_configured' => true,
            ];
        } catch (\Throwable) {
            return [
                'mode' => 'web',
                'enabled' => true,
                'configured' => true,
                'status' => 'unreachable',
                'detail' => 'The private bridge could not be reached from the application.',
                'qr_code' => null,
                'webhook_configured' => true,
            ];
        }
    }

    private function configured(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
