<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Communications\WhatsAppBridgeClient;
use App\Models\WhatsAppAccount;
use Throwable;

final readonly class DeleteWhatsAppAccount implements Action
{
    public function __construct(private WhatsAppBridgeClient $bridge) {}

    public function handle(WhatsAppAccount $account): bool
    {
        try {
            if ($this->bridge->configured()) {
                $this->bridge->remove($account);
            }
        } catch (Throwable $exception) {
            report($exception);
            $account->forceFill([
                'status' => 'unreachable',
                'last_error' => $exception->getMessage(),
            ])->save();

            return false;
        }

        $account->delete();

        return true;
    }
}
