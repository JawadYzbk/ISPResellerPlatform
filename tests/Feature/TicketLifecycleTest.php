<?php

use App\Domain\Support\TicketStateMachine;
use App\Enums\TicketStatus;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tracks ticket status changes and blocks reopening a closed ticket', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $ticket = Ticket::create(['number' => 'TCK-00001', 'subject' => 'No signal', 'description' => 'Customer reports an outage', 'status' => TicketStatus::Open]);
    $machine = app(TicketStateMachine::class);

    $machine->transition($ticket, TicketStatus::InProgress);
    $machine->transition($ticket, TicketStatus::Resolved);
    $machine->transition($ticket, TicketStatus::Closed);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Closed)
        ->and($ticket->events()->count())->toBe(3)
        ->and(fn (): Ticket => $machine->transition($ticket, TicketStatus::Open))->toThrow(DomainException::class);
});
