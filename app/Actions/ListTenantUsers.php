<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListTenantUsers implements Action
{
    /** @return LengthAwarePaginator<int, User> */
    public function handle(?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->when($search, function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('role', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
