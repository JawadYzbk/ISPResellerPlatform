<?php

namespace App\Actions;

use Illuminate\Support\Facades\Auth;

final readonly class AuthenticateUser
{
    public function handle(string $email, string $password, bool $remember = false): bool
    {
        return Auth::attempt(['email' => $email, 'password' => $password], $remember);
    }
}
