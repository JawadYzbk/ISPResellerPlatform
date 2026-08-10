<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

final readonly class IssueApiToken implements Action
{
    /** @param list<string> $abilities */
    public function handle(User $user, string $deviceName, array $abilities, ?string $deviceId = null): NewAccessToken
    {
        $user->forceFill(['last_authenticated_at' => now()])->save();
        $token = $user->createToken($deviceName, $abilities);

        if ($deviceId !== null) {
            $token->accessToken->forceFill(['device_id' => $deviceId])->save();
        }

        return $token;
    }
}
