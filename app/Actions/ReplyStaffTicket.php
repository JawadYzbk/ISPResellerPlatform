<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReplyStaffTicket implements Action
{
    public function handle(Ticket $ticket, User $actor, string $body, string $visibility = 'public'): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $actor, $body, $visibility): TicketMessage {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status === TicketStatus::Closed) {
                throw new DomainException('Closed tickets cannot receive new replies.');
            }

            if ($locked->status === TicketStatus::Resolved) {
                $locked->forceFill(['status' => TicketStatus::InProgress])->save();
                TicketEvent::create([
                    'ticket_id' => $locked->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'status_changed',
                    'from_status' => TicketStatus::Resolved->value,
                    'to_status' => TicketStatus::InProgress->value,
                    'metadata' => ['reason' => 'staff_reply'],
                ]);
            }

            $message = TicketMessage::create([
                'ticket_id' => $locked->id,
                'author_type' => 'staff',
                'author_id' => $actor->id,
                'body' => trim($body),
                'visibility' => $visibility,
            ]);
            TicketEvent::create([
                'ticket_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => 'message_added',
                'metadata' => ['author_type' => 'staff', 'visibility' => $visibility],
            ]);

            return $message->refresh();
        });
    }
}
