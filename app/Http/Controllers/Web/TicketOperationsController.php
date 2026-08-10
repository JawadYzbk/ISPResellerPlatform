<?php

namespace App\Http\Controllers\Web;

use App\Actions\AssignTicket;
use App\Actions\CreateStaffTicket;
use App\Actions\ListTickets;
use App\Actions\ReplyStaffTicket;
use App\Domain\Support\TicketStateMachine;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Service;
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
    public function create(Request $request, Customer $customer): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.create'), 403);
        $customer->load('services.plan');

        return Inertia::render('Operations/TicketCreate', [
            'customer' => $customer->only(['public_id', 'code', 'first_name', 'last_name']),
            'services' => $customer->services->map(fn (Service $service): array => [
                'public_id' => $service->public_id,
                'username' => $service->username,
                'plan' => $service->plan?->name,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request, Customer $customer, CreateStaffTicket $create): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.create'), 403);
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'max:64'],
            'priority' => ['required', Rule::in(['critical', 'high', 'normal', 'low'])],
            'service_public_id' => ['nullable', 'string'],
        ]);

        $ticket = $create->handle(
            $customer,
            $user,
            (string) $validated['subject'],
            (string) $validated['description'],
            (string) $validated['priority'],
            (string) $validated['category'],
            filled($validated['service_public_id'] ?? null) ? (string) $validated['service_public_id'] : null,
        );

        return redirect()->route('operations.tickets.show', $ticket->public_id)->with('success', "Ticket {$ticket->number} created.");
    }

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
        $assignees = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('role', ['tenant_owner', 'operations_manager', 'support_agent', 'technician', 'network_administrator', 'reseller_owner'])
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->filter(fn (User $candidate): bool => $candidate->can('tickets.view'))
            ->values();

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
            'assignees' => $assignees,
            'canAssign' => $user->can('tickets.assign'),
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
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => ['nullable', Rule::in(['public', 'internal'])],
        ]);
        $reply->handle($ticket, $user, $validated['body'], $validated['visibility'] ?? 'public');

        return redirect()->route('operations.tickets.show', $ticket->public_id)->with('success', 'Reply posted.');
    }

    public function assign(Request $request, Ticket $ticket, AssignTicket $assign): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.assign'), 403);
        $validated = $request->validate(['assignee_id' => ['nullable', 'integer', 'min:1']]);
        $assignee = isset($validated['assignee_id'])
            ? User::query()->where('tenant_id', $ticket->tenant_id)->findOrFail($validated['assignee_id'])
            : null;
        $assign->handle($ticket, $assignee, $user);

        return redirect()->route('operations.tickets.show', $ticket->public_id)->with('success', $assignee === null ? 'Ticket unassigned.' : "Ticket assigned to {$assignee->name}.");
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
            'assignee' => $ticket->assignee === null ? null : ['id' => $ticket->assignee->id, 'name' => $ticket->assignee->name],
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
