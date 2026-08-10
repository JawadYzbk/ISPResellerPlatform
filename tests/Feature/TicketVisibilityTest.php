<?php

use App\Actions\GetPortalTicket;
use App\Actions\ListPortalTickets;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps internal staff notes out of customer ticket payloads and counts', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $ticket = Ticket::create(['number' => 'TCK-VISIBILITY-001', 'customer_id' => $customer->id, 'subject' => 'Visibility', 'description' => 'Test', 'priority' => 'normal', 'status' => 'open']);
    TicketMessage::create(['ticket_id' => $ticket->id, 'author_type' => 'customer', 'author_id' => $customer->id, 'body' => 'Public message', 'visibility' => 'public']);
    TicketMessage::create(['ticket_id' => $ticket->id, 'author_type' => 'staff', 'body' => 'Internal routing note', 'visibility' => 'internal']);

    $detail = app(GetPortalTicket::class)->handle($customer, $ticket->public_id);
    $list = app(ListPortalTickets::class)->handle($customer);

    expect($detail['messages'])->toHaveCount(1)
        ->and($detail['messages'][0]['body'])->toBe('Public message')
        ->and($list[0]['message_count'])->toBe(1);
});
