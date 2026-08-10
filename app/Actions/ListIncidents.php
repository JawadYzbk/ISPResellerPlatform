<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListIncidents implements Action
{
    /** @return LengthAwarePaginator<int, Incident> */
    public function handle(?string $status = null, ?string $severity = null, ?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        $needle = trim((string) $search);

        return Incident::query()
            ->with(['router.pop', 'service.customer'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($severity, fn (Builder $query) => $query->where('severity', $severity))
            ->when($needle !== '', function (Builder $query) use ($needle): void {
                $like = '%'.$needle.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('type', 'like', $like)
                        ->orWhereHas('router', fn (Builder $router) => $router->where('name', 'like', $like)->orWhere('host', 'like', $like))
                        ->orWhereHas('service', fn (Builder $service) => $service->where('username', 'like', $like))
                        ->orWhereHas('service.customer', fn (Builder $customer) => $customer->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('code', 'like', $like));
                });
            })
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('opened_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
