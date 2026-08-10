<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListCurrentSessions implements Action
{
    /** @return LengthAwarePaginator<int, CurrentSession> */
    public function handle(?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        $needle = trim((string) $search);

        return CurrentSession::query()
            ->with(['service.customer', 'service.plan', 'service.router'])
            ->whereNull('stopped_at')
            ->when($needle !== '', function (Builder $query) use ($needle): void {
                $like = '%'.$needle.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('username', 'like', $like)
                        ->orWhere('acct_session_id', 'like', $like)
                        ->orWhere('nasname', 'like', $like)
                        ->orWhere('framed_ip', 'like', $like)
                        ->orWhereHas('service.customer', function (Builder $customer) use ($like): void {
                            $customer->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        });
                });
            })
            ->orderByDesc('last_seen_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
