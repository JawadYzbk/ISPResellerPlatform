<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Support\TicketStateMachine;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use DomainException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ReplyPortalTicket implements Action
{
    public function __construct(private TicketStateMachine $stateMachine, private GetPortalTicket $get) {}

    public function handle(Customer $customer, string $publicId, string $body): array
    {
        $ticket = Ticket::query()->where('customer_id', $customer->id)->where('public_id', $publicId)->first();
        if ($ticket === null) {
            throw new NotFoundHttpException;
        }
        if ($ticket->status === TicketStatus::Closed) {
            throw new DomainException('Closed tickets cannot receive new replies.');
        }
        if ($ticket->status === TicketStatus::Resolved) {
            $ticket = $this->stateMachine->transition($ticket, TicketStatus::InProgress, metadata: ['reason' => 'customer_reply']);
        }

        DB::transaction(function () use ($ticket, $customer, $body): void {
            TicketMessage::create(['ticket_id' => $ticket->id, 'author_type' => 'customer', 'author_id' => $customer->id, 'body' => $body, 'visibility' => 'public']);
            TicketEvent::create(['ticket_id' => $ticket->id, 'event_type' => 'message_added', 'metadata' => ['author_type' => 'customer']]);
        });

        return $this->get->handle($customer, $publicId);
    }
}
