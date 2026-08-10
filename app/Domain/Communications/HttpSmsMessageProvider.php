<?php

namespace App\Domain\Communications;

use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class HttpSmsMessageProvider implements MessageProvider
{
    public function send(Message $message): MessageDeliveryResult
    {
        $endpoint = config('services.sms.endpoint');
        $token = config('services.sms.token');
        if (! is_string($endpoint) || $endpoint === '' || ! is_string($token) || $token === '') {
            return MessageDeliveryResult::failed('sms', 'provider_not_configured');
        }

        try {
            $response = Http::withToken($token)->timeout(10)->post($endpoint, ['to' => $message->recipient, 'message' => $message->body, 'sender' => config('services.sms.sender')]);
        } catch (ConnectionException $exception) {
            return MessageDeliveryResult::failed('sms', 'provider_unreachable: '.$exception->getMessage());
        }
        if ($response->failed()) {
            return MessageDeliveryResult::failed('sms', 'provider_rejected: HTTP '.$response->status());
        }

        $id = $response->json('id');

        return MessageDeliveryResult::sent('sms', is_string($id) ? $id : null);
    }
}
