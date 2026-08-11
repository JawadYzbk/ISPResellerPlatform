<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Tenant;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Str;
use LogicException;

final readonly class EnsureWhatsAppAccount implements Action
{
    public function handle(Tenant $tenant): WhatsAppAccount
    {
        $existing = $tenant->whatsappAccounts()->oldest('id')->first();
        if ($existing instanceof WhatsAppAccount) {
            return $existing;
        }

        $bridgeId = (string) config('services.whatsapp.web.client_id', 'isp-manager');
        if (WhatsAppAccount::withoutGlobalScopes()->where('bridge_id', $bridgeId)->exists()) {
            $bridgeId = 'wa-'.Str::lower((string) $tenant->public_id).'-primary';
        }

        $account = $tenant->whatsappAccounts()->create([
            'label' => 'Primary WhatsApp',
            'job' => 'general',
            'bridge_id' => $bridgeId,
            'status' => 'disconnected',
            'is_active' => true,
        ]);

        if (! $account instanceof WhatsAppAccount) {
            throw new LogicException('The WhatsApp account relation returned an unexpected model.');
        }

        return $account;
    }
}
