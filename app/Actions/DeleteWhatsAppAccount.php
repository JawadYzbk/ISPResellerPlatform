<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Communications\WhatsAppBridgeClient;
use App\Models\WhatsAppAccount;

final readonly class DeleteWhatsAppAccount implements Action
{
    public function __construct(private WhatsAppBridgeClient $bridge) {}

    public function handle(WhatsAppAccount $account): void
    {
        if ($this->bridge->configured()) {
            $this->bridge->remove($account);
        }

        $account->delete();
    }
}
