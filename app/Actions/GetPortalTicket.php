<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GetPortalTicket implements Action
{
    /** @return array<string, mixed> */
    public function handle(Customer $customer, string $publicId): array
    {
        $ticket = Ticket::query()->where('customer_id', $customer->id)->where('public_id', $publicId)->with(['messages' => fn ($query) => $query->where('visibility', 'public')])->first();
        if ($ticket === null) {
            throw new NotFoundHttpException;
        }

        return [
            'uuid' => $ticket->public_id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'category' => $ticket->metadata['category'] ?? 'other',
            'priority' => $ticket->priority,
            'status' => $ticket->status->value,
            'satisfaction_rating' => $ticket->satisfaction_rating,
            'description' => $ticket->description,
            'due_at' => $ticket->due_at?->toIso8601String(),
            'messages' => $ticket->messages->map(fn (TicketMessage $message): array => [
                'uuid' => $message->public_id,
                'author_type' => $message->author_type,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
