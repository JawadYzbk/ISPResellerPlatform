<?php

namespace App\Actions;

use Illuminate\Support\Facades\Auth;

final readonly class LogoutUser
{
    public function handle(): void
    {
        Auth::logout();
    }
}
