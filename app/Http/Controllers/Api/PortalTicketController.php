<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreatePortalTicket;
use App\Actions\GetPortalTicket;
use App\Actions\ListPortalTickets;
use App\Actions\RatePortalTicket;
use App\Actions\ReplyPortalTicket;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PortalTicketController extends Controller
{
    public function index(Request $request, ListPortalTickets $list): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);

        return response()->json(['data' => $list->handle($customer)]);
    }

    public function store(Request $request, CreatePortalTicket $create): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $validated = $request->validate([
            'category' => ['required', Rule::in(['no_service', 'slow', 'billing', 'relocation', 'other'])],
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'service_uuid' => ['nullable', 'string'],
        ]);
        $ticket = $create->handle($customer, $validated['subject'], $validated['description'], $validated['category'], $validated['service_uuid'] ?? null);

        return response()->json(['data' => ['uuid' => $ticket->public_id, 'number' => $ticket->number, 'status' => $ticket->status->value]], 201);
    }

    public function show(Request $request, Tenant $tenant, string $ticket, GetPortalTicket $get): JsonResponse
    {
        return $this->showForCustomer($request, $ticket, $get);
    }

    public function showRoot(Request $request, string $ticket, GetPortalTicket $get): JsonResponse
    {
        return $this->showForCustomer($request, $ticket, $get);
    }

    private function showForCustomer(Request $request, string $ticket, GetPortalTicket $get): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);

        return response()->json(['data' => $get->handle($customer, $ticket)]);
    }

    public function message(Request $request, Tenant $tenant, string $ticket, ReplyPortalTicket $reply): JsonResponse
    {
        return $this->messageForCustomer($request, $ticket, $reply);
    }

    public function messageRoot(Request $request, string $ticket, ReplyPortalTicket $reply): JsonResponse
    {
        return $this->messageForCustomer($request, $ticket, $reply);
    }

    public function rating(Request $request, Tenant $tenant, string $ticket, RatePortalTicket $rate): JsonResponse
    {
        return $this->rateForCustomer($request, $ticket, $rate);
    }

    public function ratingRoot(Request $request, string $ticket, RatePortalTicket $rate): JsonResponse
    {
        return $this->rateForCustomer($request, $ticket, $rate);
    }

    private function messageForCustomer(Request $request, string $ticket, ReplyPortalTicket $reply): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        return response()->json(['data' => $reply->handle($customer, $ticket, $validated['body'])]);
    }

    private function rateForCustomer(Request $request, string $ticket, RatePortalTicket $rate): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $validated = $request->validate(['rating' => ['required', 'integer', 'between:1,5']]);

        try {
            $payload = $rate->handle($customer, $ticket, (int) $validated['rating']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['rating' => $exception->getMessage()]);
        }

        return response()->json(['data' => $payload]);
    }
}
