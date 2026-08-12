<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Ticket;

final readonly class ListPortalTickets implements Action
{
    /** @return list<array<string, mixed>> */
    public function handle(Customer $customer): array
    {
        $tickets = Ticket::query()->where('customer_id', $customer->id)->withCount(['messages as public_messages_count' => fn ($query) => $query->where('visibility', 'public')])->latest()->limit(50)->get();
        $payload = [];
        foreach ($tickets as $ticket) {
            $payload[] = [
                'uuid' => $ticket->public_id,
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'category' => $ticket->metadata['category'] ?? 'other',
                'priority' => $ticket->priority,
                'status' => $ticket->status->value,
                'satisfaction_rating' => $ticket->satisfaction_rating,
                'due_at' => $ticket->due_at?->toIso8601String(),
                'updated_at' => $ticket->updated_at?->toIso8601String(),
                'message_count' => $ticket->public_messages_count,
            ];
        }

        return $payload;
    }
}
