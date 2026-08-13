<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Communications\TemplateRenderer;
use App\Enums\MessageStatus;
use App\Jobs\DeliverMessage;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\DB;

final readonly class QueueMessage implements Action
{
    public function __construct(private TemplateRenderer $renderer) {}

    /** @param array<string, scalar|null> $variables @param array<string, mixed> $metadata */
    public function handle(MessageTemplate $template, string $recipient, string $channel, string $locale, string $idempotencyKey, array $variables = [], ?Customer $customer = null, array $metadata = [], ?WhatsAppAccount $whatsappAccount = null): Message
    {
        $recipient = $this->normalizeRecipient($recipient, $channel);
        $body = $this->renderer->render($template, $variables);
        $contentHash = hash('sha256', $channel."\0".$recipient."\0".$body);

        return DB::transaction(function () use ($template, $recipient, $channel, $locale, $idempotencyKey, $body, $contentHash, $customer, $metadata, $whatsappAccount): Message {
            Tenant::query()->lockForUpdate()->findOrFail($template->tenant_id);
            $existing = Message::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof Message) {
                return $existing;
            }

            if ($channel === 'whatsapp') {
                $duplicateWindow = (int) config('services.whatsapp.safety.duplicate_window_seconds', 120);
                if ($duplicateWindow > 0) {
                    $duplicate = Message::query()
                        ->where('channel', 'whatsapp')
                        ->where('recipient', $recipient)
                        ->where('content_hash', $contentHash)
                        ->whereIn('status', ['queued', 'sent', 'delivered'])
                        ->where('created_at', '>=', now()->subSeconds($duplicateWindow))
                        ->latest('id')
                        ->first();
                    if ($duplicate instanceof Message) {
                        return $duplicate;
                    }
                }
            }

            $message = Message::create([
                'customer_id' => $customer?->id,
                'whatsapp_account_id' => $whatsappAccount?->id,
                'channel' => $channel,
                'recipient' => $recipient,
                'template_key' => $template->key,
                'locale' => $locale,
                'subject' => $template->subject,
                'body' => $body,
                'status' => MessageStatus::Queued,
                'idempotency_key' => $idempotencyKey,
                'content_hash' => $contentHash,
                'metadata' => $metadata,
            ]);
            DeliverMessage::dispatch($message->id)->afterCommit();

            return $message;
        });
    }

    private function normalizeRecipient(string $recipient, string $channel): string
    {
        if ($channel !== 'whatsapp') {
            return $recipient;
        }

        return preg_replace('/\D+/', '', trim($recipient)) ?: trim($recipient);
    }
}
