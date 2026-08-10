<?php

namespace App\Domain\Communications;

use App\Models\Message;

final readonly class MessageProviderManager
{
    public function __construct(private NullMessageProvider $fallback) {}

    public function send(Message $message): MessageDeliveryResult
    {
        if (app()->bound(FakeMessageProvider::class)) {
            return app(FakeMessageProvider::class)->send($message);
        }

        $message->loadMissing('customer');
        $channels = [$message->channel, ...$this->fallbackChannels($message)];
        $lastFailure = null;
        $attempted = [];

        foreach (array_values(array_unique($channels)) as $channel) {
            if (! $this->configured($channel)) {
                continue;
            }
            $recipient = $this->recipient($message, $channel);
            if ($recipient === null) {
                continue;
            }

            $candidate = clone $message;
            $candidate->setAttribute('channel', $channel);
            $candidate->setAttribute('recipient', $recipient);
            $attempted[] = $channel;
            $result = $this->sendConfigured($candidate, $channel);
            if ($result->status === 'sent') {
                return $result->withMetadata([
                    'delivered_channel' => $channel,
                    'attempted_channels' => $attempted,
                    ...($channel === $message->channel ? [] : ['fallback_from' => $message->channel]),
                ]);
            }
            $lastFailure = $result;
        }

        return $lastFailure?->withMetadata(['attempted_channels' => $attempted]) ?? $this->fallback->send($message);
    }

    /** @return list<string> */
    private function fallbackChannels(Message $message): array
    {
        $fallback = $message->metadata['fallback_channels'] ?? [];

        return is_array($fallback) ? array_values(array_filter($fallback, is_string(...))) : [];
    }

    private function configured(string $channel): bool
    {
        return match ($channel) {
            'whatsapp' => config('services.whatsapp.mode') === 'web'
                ? (bool) config('services.whatsapp.web.enabled') && (bool) config('services.whatsapp.web.endpoint') && (bool) config('services.whatsapp.web.token')
                : (bool) config('services.whatsapp.token') && (bool) config('services.whatsapp.phone_number_id'),
            'sms' => (bool) config('services.sms.endpoint') && (bool) config('services.sms.token'),
            'push' => (bool) config('services.fcm.endpoint') && (bool) config('services.fcm.token'),
            'email' => (bool) config('services.notifications.email_enabled', false),
            default => false,
        };
    }

    private function recipient(Message $message, string $channel): ?string
    {
        $recipient = $channel === 'email' ? $message->customer?->email : $message->customer?->phone;
        if (! is_string($recipient) || trim($recipient) === '') {
            return $channel === $message->channel ? $message->recipient : null;
        }

        return $recipient;
    }

    private function sendConfigured(Message $message, string $channel): MessageDeliveryResult
    {
        return match ($channel) {
            'whatsapp' => config('services.whatsapp.mode') === 'web'
                ? app(WhatsAppWebMessageProvider::class)->send($message)
                : app(WhatsAppCloudMessageProvider::class)->send($message),
            'sms' => app(HttpSmsMessageProvider::class)->send($message),
            'push' => app(FcmMessageProvider::class)->send($message),
            'email' => app(MailMessageProvider::class)->send($message),
            default => MessageDeliveryResult::failed('unknown', 'provider_not_supported'),
        };
    }
}
