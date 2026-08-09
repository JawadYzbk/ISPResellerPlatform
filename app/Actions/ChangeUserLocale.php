<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;

final readonly class ChangeUserLocale implements Action
{
    public function handle(User $user, string $locale): User
    {
        abort_unless(in_array($locale, ['en', 'ar'], true), 422, 'Unsupported locale.');
        $user->forceFill(['locale' => $locale])->save();

        return $user->refresh();
    }
}
