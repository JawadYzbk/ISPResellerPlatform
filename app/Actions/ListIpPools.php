<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\IpPool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ListIpPools implements Action
{
    /** @return Collection<int, IpPool> */
    public function handle(): Collection
    {
        return IpPool::query()
            ->with('router')
            ->withCount('addresses')
            ->withCount(['addresses as free_addresses_count' => fn (Builder $query): Builder => $query->where('status', 'free')])
            ->orderBy('name')
            ->get();
    }
}
