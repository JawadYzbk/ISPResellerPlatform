<?php

use App\Actions\AutoCloseResolvedTickets;
use App\Domain\Support\TicketSlaClock;
use App\Enums\TicketStatus;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates a high priority SLA across tenant business hours and weekends', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'timezone' => 'Asia/Beirut', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $opened = CarbonImmutable::parse('2026-08-07 16:00:00', 'Asia/Beirut');

    $due = app(TicketSlaClock::class)->dueAt($tenant, 'high', $opened);

    expect($due->setTimezone('Asia/Beirut')->toDateTimeString())->toBe('2026-08-10 15:00:00');
});

it('auto-closes resolved tickets only after the configured window', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $old = Ticket::create(['number' => 'TCK-OLD', 'subject' => 'Old', 'description' => 'Resolved', 'priority' => 'normal', 'status' => TicketStatus::Resolved, 'resolved_at' => now()->subDays(3)]);
    $recent = Ticket::create(['number' => 'TCK-NEW', 'subject' => 'New', 'description' => 'Resolved', 'priority' => 'normal', 'status' => TicketStatus::Resolved, 'resolved_at' => now()->subHours(2)]);

    expect(app(AutoCloseResolvedTickets::class)->handle($tenant))->toBe(1)
        ->and($old->refresh()->status)->toBe(TicketStatus::Closed)
        ->and($recent->refresh()->status)->toBe(TicketStatus::Resolved);
});
