<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final readonly class ListSessionDevices implements Action
{
    /** @return list<array{id: string, ip_address: string|null, user_agent: string|null, last_activity: int, current: bool}> */
    public function handle(User $user, ?string $currentSessionId = null): array
    {
        return DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()->map(fn (object $session): array => [
            'id' => Crypt::encryptString((string) $session->id),
            'ip_address' => $session->ip_address,
            'user_agent' => $session->user_agent,
            'last_activity' => (int) $session->last_activity,
            'current' => $session->id === $currentSessionId,
        ])->values()->all();
    }
}
