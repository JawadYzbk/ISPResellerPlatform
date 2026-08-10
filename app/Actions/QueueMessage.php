<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Communications\TemplateRenderer;
use App\Enums\MessageStatus;
use App\Jobs\DeliverMessage;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use Illuminate\Support\Facades\DB;

final readonly class QueueMessage implements Action
{
    public function __construct(private TemplateRenderer $renderer) {}

    /** @param array<string, scalar|null> $variables */
    public function handle(MessageTemplate $template, string $recipient, string $channel, string $locale, string $idempotencyKey, array $variables = [], ?Customer $customer = null): Message
    {
        $existing = Message::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof Message) {
            return $existing;
        }

        return DB::transaction(function () use ($template, $recipient, $channel, $locale, $idempotencyKey, $variables, $customer): Message {
            $message = Message::create([
                'customer_id' => $customer?->id,
                'channel' => $channel,
                'recipient' => $recipient,
                'template_key' => $template->key,
                'locale' => $locale,
                'subject' => $template->subject,
                'body' => $this->renderer->render($template, $variables),
                'status' => MessageStatus::Queued,
                'idempotency_key' => $idempotencyKey,
            ]);
            DeliverMessage::dispatch($message->id)->afterCommit();

            return $message;
        });
    }
}
