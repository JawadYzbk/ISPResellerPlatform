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
        private WhatsAppDeliveryGuard $guard,
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

        $decision = $this->guard->claim($account);
        if (! $decision->allowed) {
            return MessageDeliveryResult::deferred('whatsapp_web', $decision->reason, $decision->retryAfter, [
                'whatsapp_account_id' => $account->id,
                'whatsapp_account_public_id' => $account->public_id,
                'whatsapp_safety_reason' => $decision->reason,
            ]);
        }

        try {
            $response = $this->bridge->send($account, $message->idempotency_key, $message->recipient, $message->body);
        } catch (Throwable $exception) {
            $this->guard->recordFailure($account);

            return MessageDeliveryResult::failed('whatsapp_web', 'provider_unreachable: '.$exception->getMessage());
        }

        $this->guard->recordSuccess($account);

        $id = $response['provider_message_id'] ?? null;

        return MessageDeliveryResult::sent('whatsapp_web', is_string($id) ? $id : null, [
            'whatsapp_account_id' => $account->id,
            'whatsapp_account_public_id' => $account->public_id,
            'whatsapp_bridge_id' => $account->bridge_id,
            'whatsapp_job' => $account->job,
            'whatsapp_replayed' => ($response['replayed'] ?? false) === true,
        ]);
    }
}
