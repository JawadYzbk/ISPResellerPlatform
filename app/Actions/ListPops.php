<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Pop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListPops implements Action
{
    /** @return LengthAwarePaginator<int, Pop> */
    public function handle(?string $status, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return Pop::query()
            ->withCount(['routers', 'upstreamLinks'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search, function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where(fn (Builder $query): Builder => $query->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
