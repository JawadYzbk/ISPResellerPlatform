<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListServices implements Action
{
    /** @return LengthAwarePaginator<int, Service> */
    public function handle(?string $search = null, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return Service::query()
            ->with(['customer', 'plan'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('username', 'like', '%'.trim($search).'%')
                    ->orWhereHas('customer', fn ($customer) => $customer->where('first_name', 'like', '%'.trim($search).'%')->orWhere('last_name', 'like', '%'.trim($search).'%')->orWhere('phone_normalized', 'like', '%'.trim($search).'%'));
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
