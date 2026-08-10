<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Router;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListRouters implements Action
{
    /** @return LengthAwarePaginator<int, Router> */
    public function handle(?string $status, int $perPage = 25): LengthAwarePaginator
    {
        return Router::query()
            ->with('pop')
            ->withCount('services')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
