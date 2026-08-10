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

        return match ($message->channel) {
            'whatsapp' => app(WhatsAppCloudMessageProvider::class)->send($message),
            'sms' => config('services.sms.endpoint') ? app(HttpSmsMessageProvider::class)->send($message) : $this->fallback->send($message),
            'push' => config('services.fcm.endpoint') ? app(FcmMessageProvider::class)->send($message) : $this->fallback->send($message),
            'email' => config('services.notifications.email_enabled', false) ? app(MailMessageProvider::class)->send($message) : $this->fallback->send($message),
            default => $this->fallback->send($message),
        };
    }
}
