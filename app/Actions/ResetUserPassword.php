<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final readonly class ResetUserPassword implements Action
{
    public function handle(string $email, string $token, string $password): string
    {
        return Password::broker()->reset(
            [
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            static function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                        'last_authenticated_at' => null,
                    ])->save();

                    DB::table('sessions')->where('user_id', $user->getKey())->delete();
                });
            },
        );
    }
}
