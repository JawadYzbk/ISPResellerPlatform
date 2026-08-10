<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class RevokeSessionDevice implements Action
{
    public function handle(User $user, string $sessionId, ?string $currentSessionId = null): bool
    {
        try {
            $internalSessionId = Crypt::decryptString($sessionId);
        } catch (Throwable) {
            return false;
        }

        if ($internalSessionId === $currentSessionId) {
            return false;
        }

        return DB::table('sessions')->where('id', $internalSessionId)->where('user_id', $user->id)->delete() > 0;
    }
}
