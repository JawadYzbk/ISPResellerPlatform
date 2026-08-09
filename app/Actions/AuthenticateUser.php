<?php

namespace App\Actions;

use App\Contracts\Action;
use Illuminate\Support\Facades\Auth;

final readonly class AuthenticateUser implements Action
{
    public function handle(string $email, string $password, bool $remember = false): bool
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            return false;
        }

        Auth::user()?->forceFill(['last_authenticated_at' => now()])->save();

        return true;
    }
}
