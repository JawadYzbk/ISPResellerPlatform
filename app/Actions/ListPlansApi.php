<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Plan;
use App\Support\Api\PlanApiResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListPlansApi implements Action
{
    public function __construct(private PlanApiResource $resource) {}

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(Plan::query())
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $term = trim((string) $value);
                    if ($term !== '') {
                        $query->where(fn (Builder $plan): Builder => $plan->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%"));
                    }
                }),
            ])
            ->allowedSorts([AllowedSort::field('created_at'), AllowedSort::field('name'), AllowedSort::field('duration_days')])
            ->defaultSort('name')
            ->with(['prices' => function ($query): void {
                $query->where('effective_from', '<=', now())
                    ->where(fn (Builder $price): Builder => $price->whereNull('effective_to')->orWhere('effective_to', '>', now()))
                    ->orderByDesc('effective_from');
            }])
            ->withCount('services')
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString()
            ->through(fn (Plan $plan): array => $this->resource->make($plan));
    }
}
