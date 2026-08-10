<?php

use App\Enums\TicketStatus;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs the resolved ticket auto-close command for each tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD', 'settings' => ['resolved_ticket_auto_close_hours' => 24]]);
    app(Tenancy::class)->set($tenant);
    $ticket = Ticket::create(['number' => 'TCK-CMD-001', 'subject' => 'Command', 'description' => 'Resolved', 'status' => TicketStatus::Resolved, 'resolved_at' => now()->subDays(2)]);

    $this->artisan('tickets:auto-close-resolved')->assertSuccessful();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Closed);
});
