<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorTaskMessage;

final readonly class DiscardCollectorTaskMessage implements Action
{
    public function handle(CollectorTaskMessage $message): void
    {
        $message->delete();
    }
}
