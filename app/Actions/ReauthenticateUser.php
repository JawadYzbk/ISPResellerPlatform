<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final readonly class ReauthenticateUser implements Action
{
    public function handle(User $user, string $password): bool
    {
        if (! Hash::check($password, $user->password)) {
            return false;
        }

        $user->forceFill(['last_authenticated_at' => now()])->save();

        return true;
    }
}
