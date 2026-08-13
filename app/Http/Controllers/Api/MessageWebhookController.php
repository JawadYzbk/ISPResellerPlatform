<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProcessMessageWebhook;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\WhatsAppAccount;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

final class MessageWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, ProcessMessageWebhook $process, Tenancy $tenancy): JsonResponse
    {
        try {
            $payload = $request->json()->all();
        } catch (JsonException) {
            abort(422, 'Webhook payload must be valid JSON.');
        }
        abort_unless(is_array($payload), 422, 'Webhook payload must be a JSON object.');

        $accountId = $payload['account_id'] ?? $payload['data']['account_id'] ?? null;
        $account = is_string($accountId) && $accountId !== ''
            ? WhatsAppAccount::withoutGlobalScopes()->where('bridge_id', $accountId)->first()
            : null;
        $tenant = $account instanceof WhatsAppAccount ? Tenant::query()->find($account->tenant_id) : null;
        $handle = fn (): JsonResponse => $this->process($request, $gateway, $payload, $process);

        return $tenant instanceof Tenant ? $tenancy->run($tenant, $handle) : $handle();
    }

    /** @param array<string, mixed> $payload */
    private function process(Request $request, string $gateway, array $payload, ProcessMessageWebhook $process): JsonResponse
    {
        $secret = config('services.webhooks.secrets.'.$gateway);
        $signature = $request->header('X-Webhook-Signature');
        abort_unless(is_string($secret) && $secret !== '' && is_string($signature) && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature), 401, 'Invalid webhook signature.');

        return response()->json($process->handle($gateway, $payload));
    }
}
