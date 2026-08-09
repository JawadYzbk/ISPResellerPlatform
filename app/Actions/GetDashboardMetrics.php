<?php

namespace App\Actions;

use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Service;

final readonly class GetDashboardMetrics
{
    /** @return array<string, int> */
    public function handle(): array
    {
        return [
            'customers' => Customer::count(),
            'activeServices' => Service::where('status', ServiceStatus::Active)->count(),
            'attention' => Service::whereIn('status', [ServiceStatus::Suspended, ServiceStatus::Pending])->count(),
            'expiringSoon' => Service::whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
        ];
    }
}
