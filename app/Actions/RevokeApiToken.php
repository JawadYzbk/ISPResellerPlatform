<?php

namespace App\Actions;

use App\Contracts\Action;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class RevokeApiToken implements Action
{
    public function handle(?string $bearerToken, ?PersonalAccessToken $currentToken): void
    {
        $token = is_string($bearerToken) && $bearerToken !== ''
            ? PersonalAccessToken::findToken($bearerToken)
            : $currentToken;

        $token?->delete();
    }
}
