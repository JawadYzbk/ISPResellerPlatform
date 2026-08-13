<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\PublicBillingLink;
use App\Models\User;
use DomainException;

final readonly class RevokePublicBillingLink implements Action
{
    public function handle(User $actor, PublicBillingLink $link): PublicBillingLink
    {
        if (! $actor->can('billing.invoices.view') || (int) $actor->tenant_id !== (int) $link->tenant_id) {
            throw new DomainException('You are not allowed to revoke this billing link.');
        }
        $link->forceFill(['revoked_at' => now()])->save();

        return $link->refresh();
    }
}
