<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AcceptInvitation implements Action
{
    public function handle(string $token, string $name, string $password): User
    {
        $invitation = Invitation::withoutGlobalScopes()->where('token_hash', hash('sha256', $token))->first();
        if (! $invitation instanceof Invitation || $invitation->accepted_at !== null || $invitation->expires_at->isPast()) {
            throw new DomainException('The invitation is invalid or expired.');
        }

        return app(Tenancy::class)->run($invitation->tenant_id, function () use ($invitation, $name, $password): User {
            return DB::transaction(function () use ($invitation, $name, $password): User {
                $user = User::create(['tenant_id' => $invitation->tenant_id, 'name' => $name, 'email' => $invitation->email, 'password' => $password, 'role' => $invitation->role]);
                $user->assignRole($invitation->role);
                $invitation->forceFill(['accepted_at' => now()])->save();

                return $user;
            });
        });
    }
}
