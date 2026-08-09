<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\UpstreamCredential;
use App\Models\User;
use DomainException;

final readonly class RevealUpstreamCredential implements Action
{
    public function handle(User $user, UpstreamCredential $credential): string
    {
        abort_unless($user->tenant_id === $credential->tenant_id && $user->can('credentials.reveal'), 403);
        if ($user->last_authenticated_at?->lessThan(now()->subMinutes(10)) ?? true) {
            throw new DomainException('Recent authentication is required to reveal credentials.');
        }
        activity('security')->performedOn($credential)->withProperties(['credential_id' => $credential->id, 'actor_id' => $user->id])->log('credential.revealed');

        return (string) $credential->secret;
    }
}
