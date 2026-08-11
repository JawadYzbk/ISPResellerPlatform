<?php

namespace App\Domain\Communications;

use App\Models\Message;
use App\Models\WhatsAppAccount;
use Throwable;

final readonly class WhatsAppWebMessageProvider implements MessageProvider
{
    public function __construct(
        private WhatsAppBridgeClient $bridge,
        private WhatsAppAccountResolver $accounts,
    ) {}

    public function send(Message $message): MessageDeliveryResult
    {
        if (! $this->bridge->configured()) {
            return MessageDeliveryResult::failed('whatsapp_web', 'provider_not_configured');
        }

        $account = $this->accounts->resolve($message);
        if (! $account instanceof WhatsAppAccount) {
            return MessageDeliveryResult::failed('whatsapp_web', 'account_not_configured');
        }

        $message->forceFill(['whatsapp_account_id' => $account->id])->save();

        try {
            $response = $this->bridge->send($account, $message->idempotency_key, $message->recipient, $message->body);
        } catch (Throwable $exception) {
            return MessageDeliveryResult::failed('whatsapp_web', 'provider_unreachable: '.$exception->getMessage());
        }

        $id = $response['provider_message_id'] ?? null;

        return MessageDeliveryResult::sent('whatsapp_web', is_string($id) ? $id : null, [
            'whatsapp_account_id' => $account->id,
            'whatsapp_account_public_id' => $account->public_id,
            'whatsapp_bridge_id' => $account->bridge_id,
            'whatsapp_job' => $account->job,
        ]);
    }
}
