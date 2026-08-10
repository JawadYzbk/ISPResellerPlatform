<?php

namespace App\Support\Api;

use App\Models\User;

final readonly class UserApiResource
{
    /** @return array<string, mixed> */
    public function make(User $user): array
    {
        $user->loadMissing('partner');

        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'partner' => $user->partner === null ? null : [
                'id' => $user->partner->public_id,
                'name' => $user->partner->name,
                'code' => $user->partner->code,
            ],
        ];
    }
}
