<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class RevokeSessionDevice implements Action
{
    public function handle(User $user, string $sessionId, ?string $currentSessionId = null): bool
    {
        if ($sessionId === $currentSessionId) {
            return false;
        }

        return DB::table('sessions')->where('id', $sessionId)->where('user_id', $user->id)->delete() > 0;
    }
}
