<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\PublicBillingLink;
use App\Support\Tenancy;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ResolvePublicBillingLink implements Action
{
    public function handle(string $token, bool $trackAccess = true): PublicBillingLink
    {
        $link = PublicBillingLink::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $token))
            ->first();
        if (! $link instanceof PublicBillingLink || $link->revoked_at !== null || $link->expires_at->isPast()) {
            throw new NotFoundHttpException('This billing link is invalid or has expired.');
        }

        app(Tenancy::class)->set((int) $link->tenant_id);
        $current = PublicBillingLink::query()->findOrFail($link->id);
        if ($trackAccess) {
            $current->forceFill([
                'last_accessed_at' => now(),
                'access_count' => $current->access_count + 1,
            ])->save();
        }

        return $current->load(['tenant', 'customer', 'invoice.lines', 'invoice.payments.allocations', 'invoice.creditNotes', 'payment.allocations.invoice']);
    }
}
