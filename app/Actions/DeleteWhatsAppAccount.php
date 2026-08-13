<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Communications\WhatsAppBridgeClient;
use App\Jobs\DeleteWhatsAppBridgeAccount;
use App\Models\WhatsAppAccount;

final readonly class DeleteWhatsAppAccount implements Action
{
    public function __construct(private WhatsAppBridgeClient $bridge) {}

    /** @return array{deleted: bool, cleanup_queued: bool} */
    public function handle(WhatsAppAccount $account): array
    {
        $cleanupQueued = $this->bridge->configured();
        $bridgeId = $account->bridge_id;
        $account->delete();

        if ($cleanupQueued) {
            DeleteWhatsAppBridgeAccount::dispatch($bridgeId)->afterCommit();
        }

        return ['deleted' => true, 'cleanup_queued' => $cleanupQueued];
    }
}
