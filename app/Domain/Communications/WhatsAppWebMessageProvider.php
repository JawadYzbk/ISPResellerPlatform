<?php

namespace App\Domain\Communications;

use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class WhatsAppWebMessageProvider implements MessageProvider
{
    public function send(Message $message): MessageDeliveryResult
    {
        $endpoint = config('services.whatsapp.web.endpoint');
        $token = config('services.whatsapp.web.token');
        if (! is_string($endpoint) || trim($endpoint) === '' || ! is_string($token) || trim($token) === '') {
            return MessageDeliveryResult::failed('whatsapp_web', 'provider_not_configured');
        }

        try {
            $response = Http::withToken($token)->acceptJson()->timeout(15)->post(rtrim($endpoint, '/').'/messages', [
                'idempotency_key' => $message->idempotency_key,
                'to' => $message->recipient,
                'body' => $message->body,
            ]);
        } catch (ConnectionException $exception) {
            return MessageDeliveryResult::failed('whatsapp_web', 'provider_unreachable: '.$exception->getMessage());
        }
        if ($response->failed()) {
            return MessageDeliveryResult::failed('whatsapp_web', 'provider_rejected: HTTP '.$response->status());
        }

        $id = $response->json('provider_message_id');

        return MessageDeliveryResult::sent('whatsapp_web', is_string($id) ? $id : null);
    }
}
