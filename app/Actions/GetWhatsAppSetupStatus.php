<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Tenant;
use App\Models\WhatsAppAccount;
use App\Support\QrCodeRenderer;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;

final readonly class GetWhatsAppSetupStatus implements Action
{
    public function __construct(
        private QrCodeRenderer $qrCode,
        private EnsureWhatsAppAccount $ensureAccount,
        private SynchronizeWhatsAppAccount $synchronizeAccount,
        private Tenancy $tenancy,
    ) {}

    /** @return array{mode: string, enabled: bool, configured: bool, status: string, detail: string|null, qr_code: string|null, webhook_configured: bool, accounts: list<array<string, mixed>>} */
    public function handle(bool $probeBridge = true, ?Tenant $tenant = null): array
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
                'accounts' => [],
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
                'accounts' => [],
            ];
        }

        if ($tenant instanceof Tenant) {
            return $this->tenancy->run(
                $tenant,
                fn (): array => $this->tenantStatus($tenant, $probeBridge, $webhookConfigured),
            );
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
                'accounts' => [],
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
                'accounts' => [],
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
                'accounts' => [],
            ];
        }
    }

    /** @return array<string, mixed> */
    private function tenantStatus(Tenant $tenant, bool $probeBridge, bool $webhookConfigured): array
    {
        $accounts = WhatsAppAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->oldest('id')
            ->get();
        if ($accounts->isEmpty()) {
            $accounts = collect([$this->ensureAccount->handle($tenant)]);
        }

        $statuses = $accounts->map(fn (WhatsAppAccount $account): array => $this->synchronizeAccount->handle($account, $probeBridge))->values()->all();
        $status = $this->summaryStatus($statuses, $probeBridge);
        $qr = collect($statuses)->first(fn (array $account): bool => is_string($account['qr_code'] ?? null));

        return [
            'mode' => 'web',
            'enabled' => true,
            'configured' => true,
            'status' => $status,
            'detail' => $this->summaryDetail($status, count($statuses)),
            'qr_code' => is_array($qr) ? $qr['qr_code'] : null,
            'webhook_configured' => $webhookConfigured,
            'accounts' => $statuses,
        ];
    }

    /** @param list<array<string, mixed>> $accounts */
    private function summaryStatus(array $accounts, bool $probeBridge): string
    {
        if (! $probeBridge) {
            return 'configured';
        }

        foreach (['qr', 'unreachable', 'auth_failure', 'authenticated', 'starting', 'disconnected', 'ready'] as $status) {
            if (collect($accounts)->contains(fn (array $account): bool => ($account['status'] ?? null) === $status)) {
                return $status;
            }
        }

        return 'unknown';
    }

    private function summaryDetail(string $status, int $accountCount): ?string
    {
        return match ($status) {
            'ready' => $accountCount === 1 ? null : $accountCount.' WhatsApp accounts are available.',
            'qr' => 'At least one WhatsApp account is waiting for a QR scan.',
            'unreachable' => 'The private WhatsApp bridge could not be reached for one or more accounts.',
            'disconnected' => 'At least one WhatsApp account needs to be paired.',
            default => 'Bridge configuration is present. Open WhatsApp setup to check pairing status.',
        };
    }

    private function configured(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
