<?php

namespace App\Domain\Communications;

use App\Models\Message;

final class NullMessageProvider implements MessageProvider
{
    public function send(Message $message): MessageDeliveryResult
    {
        return MessageDeliveryResult::sent('outbox', 'outbox-'.$message->public_id);
    }
}
