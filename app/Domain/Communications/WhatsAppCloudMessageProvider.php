<?php

namespace App\Domain\Communications;

use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class WhatsAppCloudMessageProvider implements MessageProvider
{
    public function send(Message $message): MessageDeliveryResult
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        if (! is_string($token) || $token === '' || ! is_string($phoneNumberId) || $phoneNumberId === '') {
            return MessageDeliveryResult::failed('whatsapp', 'provider_not_configured');
        }

        try {
            $response = Http::withToken($token)->timeout(10)->post('https://graph.facebook.com/v20.0/'.$phoneNumberId.'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $message->recipient,
                'type' => 'text',
                'text' => ['body' => $message->body],
            ]);
        } catch (ConnectionException $exception) {
            return MessageDeliveryResult::failed('whatsapp', 'provider_unreachable: '.$exception->getMessage());
        }
        if ($response->failed()) {
            return MessageDeliveryResult::failed('whatsapp', 'provider_rejected: HTTP '.$response->status());
        }

        $id = $response->json('messages.0.id');

        return MessageDeliveryResult::sent('whatsapp', is_string($id) ? $id : null);
    }
}
