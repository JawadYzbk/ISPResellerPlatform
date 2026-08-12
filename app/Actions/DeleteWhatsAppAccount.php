<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Communications\WhatsAppBridgeClient;
use App\Jobs\DeleteWhatsAppBridgeAccount;
use App\Models\WhatsAppAccount;
use Throwable;

final readonly class DeleteWhatsAppAccount implements Action
{
    public function __construct(private WhatsAppBridgeClient $bridge) {}

    /** @return array{deleted: bool, cleanup_queued: bool} */
    public function handle(WhatsAppAccount $account): array
    {
        try {
            if ($this->bridge->configured()) {
                $this->bridge->remove($account);
            }
        } catch (Throwable $exception) {
            report($exception);
            $bridgeId = $account->bridge_id;
            $account->delete();
            DeleteWhatsAppBridgeAccount::dispatch($bridgeId)->afterCommit();

            return ['deleted' => true, 'cleanup_queued' => true];
        }

        $account->delete();

        return ['deleted' => true, 'cleanup_queued' => false];
    }
}
