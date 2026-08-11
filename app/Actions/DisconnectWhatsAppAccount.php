<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Communications\WhatsAppBridgeClient;
use App\Models\WhatsAppAccount;
use Throwable;

final readonly class DisconnectWhatsAppAccount implements Action
{
    public function __construct(private WhatsAppBridgeClient $bridge, private SynchronizeWhatsAppAccount $synchronize) {}

    public function handle(WhatsAppAccount $account): WhatsAppAccount
    {
        try {
            if ($this->bridge->configured()) {
                $this->bridge->disconnect($account);
            }
        } catch (Throwable $exception) {
            $account->forceFill(['status' => 'unreachable', 'last_error' => $exception->getMessage()])->save();

            return $account->refresh();
        }

        $this->synchronize->handle($account);

        return $account->refresh();
    }
}
