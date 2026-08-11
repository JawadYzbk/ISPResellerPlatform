<?php

namespace App\Actions;

use App\Contracts\Action;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final readonly class SendPasswordResetLink implements Action
{
    public function handle(string $email): string
    {
        return Password::broker()->sendResetLink([
            'email' => Str::lower(trim($email)),
        ]);
    }
}
