<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Communications\WhatsAppBridgeClient;
use App\Models\WhatsAppAccount;
use App\Support\QrCodeRenderer;
use Throwable;

final readonly class SynchronizeWhatsAppAccount implements Action
{
    public function __construct(private WhatsAppBridgeClient $bridge, private QrCodeRenderer $qrCode) {}

    /** @return array<string, mixed> */
    public function handle(WhatsAppAccount $account, bool $probe = true): array
    {
        $payload = [
            'status' => $account->status,
            'phone' => $account->phone,
            'push_name' => $account->push_name,
            'lastError' => $account->last_error,
            'readyAt' => $account->last_ready_at?->toIso8601String(),
        ];

        if ($probe && $this->bridge->configured()) {
            try {
                $payload = $this->bridge->status($account);
            } catch (Throwable $exception) {
                $payload = [...$payload, 'status' => 'unreachable', 'lastError' => $exception->getMessage()];
            }
        }

        $status = is_string($payload['status'] ?? null) ? $payload['status'] : 'unknown';
        $phone = is_string($payload['phone'] ?? null) ? $payload['phone'] : null;
        $pushName = is_string($payload['push_name'] ?? null) ? $payload['push_name'] : null;
        $lastError = is_string($payload['lastError'] ?? null) ? $payload['lastError'] : null;
        $readyAt = is_string($payload['readyAt'] ?? null) ? $payload['readyAt'] : $account->last_ready_at?->toIso8601String();

        $account->forceFill([
            'status' => $status,
            'phone' => $phone,
            'push_name' => $pushName,
            'last_error' => $lastError,
            'last_ready_at' => $readyAt,
        ])->save();

        $rawQr = $status === 'qr' && is_string($payload['qr'] ?? null) ? $payload['qr'] : null;

        return [
            'id' => $account->public_id,
            'label' => $account->label,
            'job' => $account->job,
            'status' => $status,
            'phone' => $phone,
            'push_name' => $pushName,
            'last_error' => $lastError,
            'last_ready_at' => $account->last_ready_at?->toIso8601String(),
            'next_send_at' => $account->next_send_at?->toIso8601String(),
            'cooldown_until' => $account->cooldown_until?->toIso8601String(),
            'failure_streak' => (int) $account->failure_streak,
            'qr_code' => $rawQr === null ? null : $this->qrCode->dataUri($rawQr),
            'is_active' => (bool) $account->is_active,
        ];
    }
}
