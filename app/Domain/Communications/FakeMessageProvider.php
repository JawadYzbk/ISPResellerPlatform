<?php

namespace App\Domain\Communications;

use App\Models\Message;

final class FakeMessageProvider implements MessageProvider
{
    private ?MessageDeliveryResult $result = null;

    public function send(Message $message): MessageDeliveryResult
    {
        return $this->result ?? MessageDeliveryResult::sent('fake', 'fake-'.$message->public_id);
    }

    public function respondWith(MessageDeliveryResult $result): self
    {
        $this->result = $result;

        return $this;
    }
}
