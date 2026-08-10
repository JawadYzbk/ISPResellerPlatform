<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProcessMessageWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessageWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, ProcessMessageWebhook $process): JsonResponse
    {
        $secret = config('services.webhooks.secrets.'.$gateway);
        $signature = $request->header('X-Webhook-Signature');
        abort_unless(is_string($secret) && $secret !== '' && is_string($signature) && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature), 401, 'Invalid webhook signature.');
        $payload = $request->json()->all();
        abort_unless(is_array($payload), 422, 'Webhook payload must be a JSON object.');

        return response()->json($process->handle($gateway, $payload));
    }
}
