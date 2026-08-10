<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Support\TicketStateMachine;
use App\Enums\TicketStatus;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

final readonly class AutoCloseResolvedTickets implements Action
{
    public function __construct(private TicketStateMachine $stateMachine) {}

    public function handle(Tenant $tenant, ?CarbonImmutable $at = null): int
    {
        return app(Tenancy::class)->run($tenant, function () use ($at, $tenant): int {
            $at ??= CarbonImmutable::now();
            $hours = (int) ($tenant->settingsData()->settings['resolved_ticket_auto_close_hours'] ?? 48);
            $closed = 0;
            Ticket::query()->where('status', TicketStatus::Resolved)->whereNotNull('resolved_at')->where('resolved_at', '<=', $at->subHours(max(1, $hours)))->select('id')->chunkById(100, function ($tickets) use (&$closed): void {
                foreach ($tickets as $ticket) {
                    $this->stateMachine->transition($ticket, TicketStatus::Closed, metadata: ['reason' => 'sla_auto_close']);
                    $closed++;
                }
            });

            return $closed;
        });
    }
}
