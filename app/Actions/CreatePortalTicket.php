<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use App\Support\DocumentNumberGenerator;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreatePortalTicket implements Action
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    public function handle(Customer $customer, string $subject, string $description, string $category = 'other', ?string $servicePublicId = null): Ticket
    {
        return DB::transaction(function () use ($customer, $subject, $description, $category, $servicePublicId): Ticket {
            $serviceId = null;
            if ($servicePublicId !== null) {
                $serviceId = Service::query()->where('customer_id', $customer->id)->where('public_id', $servicePublicId)->value('id');
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
                'priority' => 'normal',
                'status' => 'open',
                'metadata' => ['category' => $category],
            ]);
            TicketEvent::create(['ticket_id' => $ticket->id, 'event_type' => 'created', 'metadata' => ['category' => $category]]);
            TicketMessage::create(['ticket_id' => $ticket->id, 'author_type' => 'customer', 'author_id' => $customer->id, 'body' => $description, 'visibility' => 'public']);

            return $ticket->refresh();
        });
    }
}
