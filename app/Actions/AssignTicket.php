<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AssignTicket implements Action
{
    public function handle(Ticket $ticket, ?User $assignee, User $actor): Ticket
    {
        if ($assignee !== null && ($assignee->tenant_id !== $ticket->tenant_id || ! $assignee->can('tickets.view'))) {
            throw new DomainException('The selected operator cannot receive support tickets.');
        }

        return DB::transaction(function () use ($ticket, $assignee, $actor): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $previousAssignee = $locked->assigned_to;
            $locked->forceFill(['assigned_to' => $assignee?->id])->save();
            if ($previousAssignee !== $locked->assigned_to) {
                TicketEvent::create([
                    'ticket_id' => $locked->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'assigned',
                    'metadata' => ['assignee_id' => $assignee?->id],
                ]);
            }

            return $locked->refresh();
        });
    }
}
