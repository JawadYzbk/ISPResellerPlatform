<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\TicketCannedResponse;

final readonly class CreateTicketCannedResponse implements Action
{
    /** @param array{title: string, body: string, category: string} $data */
    public function handle(array $data): TicketCannedResponse
    {
        return TicketCannedResponse::create($data + ['is_active' => true]);
    }
}
