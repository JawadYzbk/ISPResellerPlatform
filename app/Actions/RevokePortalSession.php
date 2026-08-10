<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\PortalSession;

final readonly class RevokePortalSession implements Action
{
    public function handle(PortalSession $session): void
    {
        $session->forceFill(['revoked_at' => now()])->save();
    }
}
