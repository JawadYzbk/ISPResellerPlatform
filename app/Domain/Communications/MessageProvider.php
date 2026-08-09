<?php

namespace App\Domain\Communications;

use App\Models\Message;

interface MessageProvider
{
    public function send(Message $message): MessageDeliveryResult;
}
