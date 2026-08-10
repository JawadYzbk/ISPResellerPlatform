<?php

namespace App\Jobs;

use App\Domain\Communications\MessageProviderManager;
use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;

final class DeliverMessage extends TenantAwareJob implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(public int $messageId, ?int $tenantId = null)
    {
        parent::__construct($tenantId);
    }

    public function handle(MessageProviderManager $providers): void
    {
        $message = Message::query()->lockForUpdate()->findOrFail($this->messageId);
        if (in_array($message->status, [MessageStatus::Delivered, MessageStatus::Sent], true)) {
            return;
        }

        $message->increment('delivery_attempts');
        $result = $providers->send($message->refresh());
        $message->forceFill([
            'status' => $result->status === 'sent' ? MessageStatus::Sent : MessageStatus::Failed,
            'provider' => $result->provider,
            'provider_message_id' => $result->providerMessageId,
            'sent_at' => $result->status === 'sent' ? now() : null,
            'failed_at' => $result->status === 'failed' ? now() : null,
            'failure_reason' => $result->status === 'failed' ? $result->message : null,
            'metadata' => [...($message->metadata ?? []), ...$result->metadata],
        ])->save();

        if ($result->status === 'failed' && $message->delivery_attempts < $this->tries) {
            throw new RuntimeException($result->message);
        }
    }
}
