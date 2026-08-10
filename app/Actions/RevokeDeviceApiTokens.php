<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class RevokeDeviceApiTokens implements Action
{
    public function handle(User $user, string $device): int
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('device_id', $device)
            ->delete();
    }
}
