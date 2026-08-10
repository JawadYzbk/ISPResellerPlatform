<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListPlans implements Action
{
    /** @return LengthAwarePaginator<int, Plan> */
    public function handle(?string $status, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        $now = now();

        return Plan::query()
            ->with(['prices' => fn ($query) => $query
                ->where('effective_from', '<=', $now)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $now))
                ->orderByDesc('effective_from')])
            ->withCount('services')
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search, fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%");
            }))
            ->orderBy('name')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
