<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListCustomers implements Action
{
    /** @return LengthAwarePaginator<int, Customer> */
    public function handle(?string $search, ?string $status, ?int $zoneId = null, ?string $expiresFrom = null, ?string $expiresTo = null, int $perPage = 20): LengthAwarePaginator
    {
        return Customer::query()
            ->with(['zone', 'services.plan'])
            ->search($search)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when($expiresFrom || $expiresTo, function ($query) use ($expiresFrom, $expiresTo): void {
                $query->whereHas('services', function ($services) use ($expiresFrom, $expiresTo): void {
                    $services->whereNotIn('status', ['terminated'])
                        ->whereNotNull('expires_at')
                        ->when($expiresFrom, fn ($services) => $services->whereDate('expires_at', '>=', $expiresFrom))
                        ->when($expiresTo, fn ($services) => $services->whereDate('expires_at', '<=', $expiresTo));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
