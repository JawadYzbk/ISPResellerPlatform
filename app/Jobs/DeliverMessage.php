<?php

namespace App\Jobs;

use App\Domain\Communications\MessageProviderManager;
use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
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
        $lock = Cache::lock('message-delivery:'.$this->tenantId.':'.$this->messageId, 120);
        if (! $lock->get()) {
            return;
        }

        try {
            $this->deliver($providers);
        } finally {
            $lock->release();
        }
    }

    private function deliver(MessageProviderManager $providers): void
    {
        $message = Message::query()->lockForUpdate()->findOrFail($this->messageId);
        if (in_array($message->status, [MessageStatus::Delivered, MessageStatus::Sent], true)) {
            return;
        }

        $message->increment('delivery_attempts');
        $result = $providers->send($message->refresh());
        if ($result->status === 'deferred') {
            $message->decrement('delivery_attempts');
            $message->forceFill([
                'status' => MessageStatus::Queued,
                'failure_reason' => null,
                'metadata' => [...($message->metadata ?? []), ...$result->metadata],
            ])->save();
            $this->release((int) ($result->metadata['retry_after'] ?? 60));

            return;
        }
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
