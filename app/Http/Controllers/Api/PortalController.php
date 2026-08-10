<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetPortalNotices;
use App\Actions\GetPortalServices;
use App\Actions\GetPortalUsage;
use App\Actions\RestartPortalSession;
use App\Actions\UpdatePortalProfile;
use App\Exceptions\PortalSessionRestartRateLimited;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PortalController extends Controller
{
    public function me(Request $request, GetPortalServices $services): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);

        $customer->load('zone');

        return response()->json([
            'public_id' => $customer->public_id,
            'code' => $customer->code,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone,
            'phone_normalized' => $customer->phone_normalized,
            'email' => $customer->email,
            'address' => $customer->address,
            'status' => $customer->status->value,
            'balance_amount' => $customer->balance_amount,
            'balance_currency' => $customer->balance_currency,
            'notification_preferences' => $customer->notification_preferences,
            'zone' => $customer->zone === null ? null : ['name' => $customer->zone->name, 'code' => $customer->zone->code],
            'services' => $services->handle($customer),
        ]);
    }

    public function updateProfile(Request $request, UpdatePortalProfile $update): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notification_preferences' => ['nullable', 'array'],
        ]);

        $updated = $update->handle($customer, $validated);

        return response()->json([
            'data' => [
                'public_id' => $updated->public_id,
                'email' => $updated->email,
                'address' => $updated->address,
                'notification_preferences' => $updated->notification_preferences,
            ],
        ]);
    }

    public function services(Request $request, GetPortalServices $services): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);

        return response()->json(['data' => $services->handle($customer)]);
    }

    public function usage(Request $request, Tenant $tenant, string $service, GetPortalUsage $usage): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $serviceModel = Service::query()->where('public_id', $service)->firstOrFail();
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = CarbonImmutable::parse($validated['from'] ?? now()->subDays(30)->toDateString());
        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString());

        return response()->json(['data' => $usage->handle($customer, $serviceModel, $from, $to)]);
    }

    public function notices(Request $request, GetPortalNotices $notices): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);

        return response()->json(['data' => $notices->handle($customer)]);
    }

    public function restartSession(Request $request, Tenant $tenant, string $service, RestartPortalSession $restart): JsonResponse
    {
        $customer = $request->attributes->get('portal_customer');
        abort_unless($customer instanceof Customer, 401);
        $serviceModel = Service::query()->where('public_id', $service)->firstOrFail();

        try {
            $command = $restart->handle($customer, $serviceModel);
        } catch (PortalSessionRestartRateLimited $exception) {
            return response()->json(['message' => $exception->getMessage()], 429)->header('Retry-After', '300');
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'service_id' => $serviceModel->public_id,
            'status' => 'restart_queued',
            'command_id' => $command->public_id,
            'session_id' => $command->payload['session_id'] ?? null,
        ], 202);
    }
}
