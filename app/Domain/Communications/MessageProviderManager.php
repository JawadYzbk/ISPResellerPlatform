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

        return $this->fallback->send($message);
    }
}
