<?php

namespace App\Domain\Communications;

use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class FcmMessageProvider implements MessageProvider
{
    public function send(Message $message): MessageDeliveryResult
    {
        $endpoint = config('services.fcm.endpoint');
        $token = config('services.fcm.token');
        if (! is_string($endpoint) || $endpoint === '' || ! is_string($token) || $token === '') {
            return MessageDeliveryResult::failed('fcm', 'provider_not_configured');
        }

        try {
            $response = Http::withToken($token)->timeout(10)->post($endpoint, ['message' => ['token' => $message->recipient, 'notification' => ['title' => $message->subject ?? config('app.name'), 'body' => $message->body]]]);
        } catch (ConnectionException $exception) {
            return MessageDeliveryResult::failed('fcm', 'provider_unreachable: '.$exception->getMessage());
        }
        if ($response->failed()) {
            return MessageDeliveryResult::failed('fcm', 'provider_rejected: HTTP '.$response->status());
        }

        $id = $response->json('name');

        return MessageDeliveryResult::sent('fcm', is_string($id) ? $id : null);
    }
}
