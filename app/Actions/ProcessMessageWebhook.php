<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class ProcessMessageWebhook implements Action
{
    /** @param array<string, mixed> $payload @return array{status: string, message_id: string|null} */
    public function handle(string $provider, array $payload): array
    {
        $providerMessageId = $payload['message_id'] ?? $payload['data']['id'] ?? $payload['id'] ?? null;
        $providerMessageId = is_string($providerMessageId) ? $providerMessageId : null;
        if ($providerMessageId === null || $providerMessageId === '') {
            return ['status' => 'ignored', 'message_id' => null];
        }

        $bridgeAccountId = $payload['account_id'] ?? $payload['data']['account_id'] ?? null;
        $bridgeAccountId = is_string($bridgeAccountId) ? $bridgeAccountId : null;

        return DB::transaction(function () use ($provider, $payload, $providerMessageId, $bridgeAccountId): array {
            $query = Message::withoutGlobalScopes()
                ->where('provider', $provider)
                ->where('provider_message_id', $providerMessageId);
            if ($bridgeAccountId !== null && $bridgeAccountId !== '') {
                $query->whereHas('whatsappAccount', fn (Builder $account): Builder => $account->where('bridge_id', $bridgeAccountId));
            }
            $message = $query->lockForUpdate()->first();
            if (! $message instanceof Message) {
                return ['status' => 'ignored', 'message_id' => null];
            }

            $status = strtolower((string) ($payload['status'] ?? $payload['event'] ?? ''));
            if ($status === 'delivered') {
                $message->forceFill(['status' => MessageStatus::Delivered, 'delivered_at' => $message->delivered_at ?? now()])->save();
            } elseif (in_array($status, ['failed', 'rejected', 'undelivered'], true)) {
                $message->forceFill(['status' => MessageStatus::Failed, 'failed_at' => $message->failed_at ?? now(), 'failure_reason' => $message->failure_reason ?? 'provider_callback_failed'])->save();
            }

            return ['status' => 'processed', 'message_id' => $message->public_id];
        });
    }
}
