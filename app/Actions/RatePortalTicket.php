<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketEvent;
use DomainException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class RatePortalTicket implements Action
{
    public function __construct(private GetPortalTicket $get) {}

    /** @return array<string, mixed> */
    public function handle(Customer $customer, string $publicId, int $rating): array
    {
        $ticket = Ticket::query()->where('customer_id', $customer->id)->where('public_id', $publicId)->first();
        if ($ticket === null) {
            throw new NotFoundHttpException;
        }
        if (! in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            throw new DomainException('Only resolved or closed tickets can be rated.');
        }

        DB::transaction(function () use ($ticket, $rating): void {
            $ticket->forceFill(['satisfaction_rating' => $rating])->save();
            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'event_type' => 'satisfaction_rated',
                'metadata' => ['rating' => $rating, 'source' => 'portal'],
            ]);
        });

        return $this->get->handle($customer, $publicId);
    }
}
