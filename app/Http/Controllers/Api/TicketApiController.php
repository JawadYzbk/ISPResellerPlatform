<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateStaffTicket;
use App\Actions\ListTicketsApi;
use App\Actions\ReplyStaffTicket;
use App\Domain\Support\TicketStateMachine;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTicketApiRequest;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Api\TicketApiResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class TicketApiController extends Controller
{
    public function index(Request $request, ListTicketsApi $listTickets): JsonResponse
    {
        abort_unless($request->user()?->can('tickets.view'), 403);

        return response()->json($listTickets->handle($request, $request->integer('per_page', 20)));
    }

    public function store(CreateTicketApiRequest $request, CreateStaffTicket $createTicket, TicketApiResource $resource): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.create'), 403);
        $data = $request->validated();
        $customer = Customer::query()->where('public_id', $data['customer_id'])->firstOrFail();
        $servicePublicId = filled($data['service_id'] ?? null) ? (string) $data['service_id'] : null;
        if ($servicePublicId !== null && ! Service::query()->where('customer_id', $customer->id)->where('public_id', $servicePublicId)->exists()) {
            throw ValidationException::withMessages(['service_id' => 'The selected service does not belong to this customer.']);
        }

        $ticket = $createTicket->handle($customer, $user, (string) $data['subject'], (string) $data['description'], (string) $data['priority'], (string) $data['category'], $servicePublicId);

        return response()->json($resource->make($ticket), 201);
    }

    public function show(Request $request, string $ticket, TicketApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('tickets.view'), 403);
        $model = Ticket::query()->where('public_id', $ticket)->firstOrFail();

        return response()->json($resource->make($model));
    }

    public function message(Request $request, string $ticket, ReplyStaffTicket $reply, TicketApiResource $resource): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.create'), 403);
        $model = Ticket::query()->where('public_id', $ticket)->firstOrFail();
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => ['nullable', Rule::in(['public', 'internal'])],
        ]);

        try {
            $reply->handle($model, $user, (string) $validated['body'], (string) ($validated['visibility'] ?? 'public'));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['body' => $exception->getMessage()]);
        }

        return response()->json($resource->make($model->refresh()));
    }

    public function status(Request $request, string $ticket, TicketStateMachine $stateMachine, TicketApiResource $resource): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('tickets.create'), 403);
        $model = Ticket::query()->where('public_id', $ticket)->firstOrFail();
        $validated = $request->validate(['status' => ['required', Rule::enum(TicketStatus::class)]]);
        $target = TicketStatus::from((string) $validated['status']);
        if ($target === TicketStatus::Closed) {
            abort_unless($user->can('tickets.close'), 403);
        }

        return response()->json($resource->make($stateMachine->transition($model, $target, $user, ['source' => 'staff_api'])));
    }
}
