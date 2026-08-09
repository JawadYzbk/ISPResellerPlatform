<?php

namespace App\Domain\Support;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class TicketStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'open' => ['in_progress', 'pending', 'resolved', 'closed'],
        'in_progress' => ['pending', 'resolved', 'closed'],
        'pending' => ['in_progress', 'resolved', 'closed'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => [],
    ];

    /** @param array<string, mixed> $metadata */
    public function transition(Ticket $ticket, TicketStatus $target, ?User $actor = null, array $metadata = []): Ticket
    {
        return DB::transaction(function () use ($ticket, $target, $actor, $metadata): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $locked->status;
            if ($from === $target || ! in_array($target->value, self::TRANSITIONS[$from->value] ?? [], true)) {
                if ($from !== $target) {
                    throw new DomainException("Ticket transition {$from->value} -> {$target->value} is not allowed.");
                }

                return $locked;
            }
            $locked->forceFill(['status' => $target, 'resolved_at' => $target === TicketStatus::Resolved ? now() : $locked->resolved_at, 'closed_at' => $target === TicketStatus::Closed ? now() : $locked->closed_at])->save();
            TicketEvent::create(['ticket_id' => $locked->id, 'actor_id' => $actor?->id, 'event_type' => 'status_changed', 'from_status' => $from->value, 'to_status' => $target->value, 'metadata' => $metadata]);

            return $locked->refresh();
        });
    }
}
