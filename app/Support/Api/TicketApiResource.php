<?php

namespace App\Support\Api;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Carbon\CarbonImmutable;

final readonly class TicketApiResource
{
    /** @return array<string, mixed> */
    public function make(Ticket $ticket, bool $includeMessages = true): array
    {
        $ticket->loadMissing(['customer', 'service', 'assignee']);
        if ($includeMessages) {
            $ticket->loadMissing('messages');
        }

        return [
            'id' => $ticket->public_id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'category' => $ticket->metadata['category'] ?? 'other',
            'priority' => $ticket->priority,
            'status' => $ticket->status->value,
            'due_at' => $this->isoDate($ticket->due_at),
            'resolved_at' => $this->isoDate($ticket->resolved_at),
            'closed_at' => $this->isoDate($ticket->closed_at),
            'customer' => $ticket->customer === null ? null : [
                'id' => $ticket->customer->public_id,
                'code' => $ticket->customer->code,
                'name' => $ticket->customer->full_name,
            ],
            'service' => $ticket->service === null ? null : [
                'id' => $ticket->service->public_id,
                'username' => $ticket->service->username,
            ],
            'assignee' => $ticket->assignee === null ? null : [
                'id' => $ticket->assignee->id,
                'name' => $ticket->assignee->name,
            ],
            'messages' => $ticket->relationLoaded('messages')
                ? $ticket->messages->map(fn (TicketMessage $message): array => [
                    'id' => $message->public_id,
                    'author_type' => $message->author_type,
                    'body' => $message->body,
                    'visibility' => $message->visibility,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
