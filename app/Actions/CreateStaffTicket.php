<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateStaffTicket implements Action
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    public function handle(Customer $customer, User $actor, string $subject, string $description, string $priority, string $category, ?string $servicePublicId = null): Ticket
    {
        if ($customer->tenant_id !== $actor->tenant_id) {
            throw new DomainException('The customer and ticket actor must belong to the same tenant.');
        }

        return DB::transaction(function () use ($customer, $actor, $subject, $description, $priority, $category, $servicePublicId): Ticket {
            $serviceId = null;
            if ($servicePublicId !== null) {
                $serviceId = Service::query()
                    ->where('customer_id', $customer->id)
                    ->where('public_id', $servicePublicId)
                    ->value('id');
                if ($serviceId === null) {
                    throw new DomainException('The selected service does not belong to this customer.');
                }
            }

            $ticket = Ticket::create([
                'number' => $this->numbers->next('ticket', 'TCK'),
                'customer_id' => $customer->id,
                'service_id' => $serviceId,
                'subject' => $subject,
                'description' => $description,
                'priority' => $priority,
                'status' => 'open',
                'metadata' => ['category' => $category, 'source' => 'staff'],
            ]);
            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $actor->id,
                'event_type' => 'created',
                'metadata' => ['category' => $category, 'source' => 'staff'],
            ]);
            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_type' => 'staff',
                'author_id' => $actor->id,
                'body' => $description,
                'visibility' => 'public',
            ]);

            return $ticket->refresh();
        });
    }
}
