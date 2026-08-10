<?php

namespace App\Http\Controllers\Web;

use App\Actions\ListTickets;
use App\Actions\ReplyStaffTicket;
use App\Domain\Support\TicketStateMachine;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class TicketOperationsController extends Controller
{
    public function index(Request $request, ListTickets $listTickets): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.view'), 403);
        $tickets = $listTickets->handle(
            $request->string('status')->toString() ?: null,
            $request->string('priority')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );
        $rows = $tickets->getCollection()->map(function (mixed $ticket): array {
            if (! $ticket instanceof Ticket) {
                throw new \LogicException('Ticket paginator contained an invalid record.');
            }

            return $this->ticketRow($ticket);
        })->values();
        $tickets = new LengthAwarePaginator(
            $rows,
            $tickets->total(),
            $tickets->perPage(),
            $tickets->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Operations/Tickets', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'priority', 'search']),
            'canMutate' => $user->can('tickets.create'),
            'canClose' => $user->can('tickets.close'),
        ]);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.view'), 403);
        $ticket->load(['customer', 'service', 'assignee', 'messages']);

        return Inertia::render('Operations/TicketShow', [
            'ticket' => [
                ...$this->ticketRow($ticket),
                'description' => $ticket->description,
                'messages' => $ticket->messages->map(fn (TicketMessage $message): array => [
                    'public_id' => $message->public_id,
                    'author_type' => $message->author_type,
                    'body' => $message->body,
                    'visibility' => $message->visibility,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->values()->all(),
            ],
            'canMutate' => $user->can('tickets.create'),
            'canClose' => $user->can('tickets.close'),
        ]);
    }

    public function status(Request $request, Ticket $ticket, TicketStateMachine $stateMachine): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.create'), 403);
        $validated = $request->validate(['status' => ['required', Rule::enum(TicketStatus::class)]]);
        $target = TicketStatus::from($validated['status']);
        if ($target === TicketStatus::Closed) {
            abort_unless($user->can('tickets.close'), 403);
        }
        $stateMachine->transition($ticket, $target, $user, ['source' => 'staff_operations']);

        return redirect()->route('operations.tickets.show', $ticket->public_id)->with('success', 'Ticket status updated.');
    }

    public function reply(Request $request, Ticket $ticket, ReplyStaffTicket $reply): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.create'), 403);
        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $reply->handle($ticket, $user, $validated['body']);

        return redirect()->route('operations.tickets.show', $ticket->public_id)->with('success', 'Reply posted.');
    }

    /** @return array<string, mixed> */
    private function ticketRow(Ticket $ticket): array
    {
        return [
            'public_id' => $ticket->public_id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'priority' => $ticket->priority,
            'status' => $ticket->status->value,
            'due_at' => $this->isoDate($ticket->due_at),
            'resolved_at' => $this->isoDate($ticket->resolved_at),
            'closed_at' => $this->isoDate($ticket->closed_at),
            'message_count' => $ticket->messages_count ?? $ticket->messages->count(),
            'customer' => $ticket->customer === null ? null : [
                'public_id' => $ticket->customer->public_id,
                'code' => $ticket->customer->code,
                'name' => $ticket->customer->full_name,
            ],
            'service' => $ticket->service === null ? null : [
                'public_id' => $ticket->service->public_id,
                'username' => $ticket->service->username,
            ],
            'assignee' => $ticket->assignee === null ? null : ['name' => $ticket->assignee->name],
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
