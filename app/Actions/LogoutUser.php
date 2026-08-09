<?php

namespace App\Actions;

use App\Contracts\Action;
use Illuminate\Support\Facades\Auth;

final readonly class LogoutUser implements Action
{
    public function handle(): void
    {
        Auth::logout();
    }
}
