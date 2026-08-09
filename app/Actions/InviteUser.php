<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class InviteUser implements Action
{
    /** @return array{invitation: Invitation, token: string} */
    public function handle(User $inviter, string $email, string $role): array
    {
        abort_unless($inviter->can('users.manage'), 403);
        $token = Str::random(64);
        $invitation = Invitation::create([
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }
}
