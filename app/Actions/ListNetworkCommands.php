<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\NetworkCommand;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListNetworkCommands implements Action
{
    /** @return LengthAwarePaginator<int, NetworkCommand> */
    public function handle(?string $status, ?string $networkState, int $perPage = 25): LengthAwarePaginator
    {
        return NetworkCommand::query()
            ->with(['service.customer', 'service.plan'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($networkState, fn ($query) => $query->whereHas('service', fn ($service) => $service->where('network_state', $networkState)))
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
