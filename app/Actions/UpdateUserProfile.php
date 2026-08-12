<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;

final readonly class UpdateUserProfile implements Action
{
    /** @param array{name: string, locale: string|null, timezone: string|null} $data */
    public function handle(User $user, array $data): User
    {
        $user->forceFill($data)->save();

        return $user->refresh();
    }
}
