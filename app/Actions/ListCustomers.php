<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListCustomers implements Action
{
    /** @return LengthAwarePaginator<int, Customer> */
    public function handle(?string $search, ?string $status, int $perPage = 20): LengthAwarePaginator
    {
        return Customer::query()
            ->with(['zone', 'services.plan'])
            ->search($search)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
