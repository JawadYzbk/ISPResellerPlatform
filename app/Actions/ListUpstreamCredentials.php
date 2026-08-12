<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\UpstreamCredential;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListUpstreamCredentials implements Action
{
    /** @return LengthAwarePaginator<int, UpstreamCredential> */
    public function handle(?string $status, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return UpstreamCredential::query()
            ->with(['batch.supplier', 'batch.supplierContract', 'assignedService.customer'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search, function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('identifier', 'like', "%{$term}%")
                        ->orWhereHas('batch', fn (Builder $batch): Builder => $batch->where('reference', 'like', "%{$term}%"))
                        ->orWhereHas('assignedService', fn (Builder $service): Builder => $service->where('username', 'like', "%{$term}%"));
                });
            })
            ->orderBy('status')
            ->orderBy('identifier')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
