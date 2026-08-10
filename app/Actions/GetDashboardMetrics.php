<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\IncidentStatus;
use App\Enums\NetworkState;
use App\Enums\PaymentStatus;
use App\Enums\ServiceStatus;
use App\Models\CurrentSession;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\NetworkCommand;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Service;
use App\Models\WorkOrder;

final readonly class GetDashboardMetrics implements Action
{
    /** @return array<string, int|string> */
    public function handle(): array
    {
        return [
            'customers' => Customer::count(),
            'activeServices' => Service::where('status', ServiceStatus::Active)->count(),
            'attention' => Service::whereIn('status', [ServiceStatus::Suspended, ServiceStatus::Pending])->count(),
            'expiringSoon' => Service::whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
            'collectionsToday' => (int) Payment::where('status', PaymentStatus::Posted)->whereDate('received_at', today())->sum('amount'),
            'collectionsCurrency' => (string) (Payment::where('status', PaymentStatus::Posted)->whereDate('received_at', today())->value('currency') ?? 'USD'),
            'networkPending' => NetworkCommand::whereIn('status', ['pending', 'running', 'failed', 'awaiting_confirmation'])->count(),
            'failedCommands' => NetworkCommand::where('status', 'failed')->count(),
            'offlineRouters' => Router::where('status', 'offline')->count(),
            'activeSessions' => CurrentSession::whereNull('stopped_at')->count(),
            'driftedServices' => Service::whereIn('network_state', [NetworkState::Drifted, NetworkState::Failed])->count(),
            'openIncidents' => Incident::where('status', IncidentStatus::Open)->count(),
            'openWorkOrders' => WorkOrder::whereNotIn('status', ['completed', 'cancelled'])->count(),
        ];
    }
}
