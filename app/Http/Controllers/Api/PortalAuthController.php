<?php

namespace App\Http\Controllers\Api;

use App\Actions\RequestPortalOtp;
use App\Actions\RevokePortalSession;
use App\Actions\VerifyPortalOtp;
use App\Http\Controllers\Controller;
use App\Models\PortalSession;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PortalAuthController extends Controller
{
    public function requestOtp(Request $request, Tenant $tenant, RequestPortalOtp $requestOtp): JsonResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:40']]);
        $result = $requestOtp->handle($tenant, $validated['phone'], $request->ip());

        return response()->json(['challenge_id' => $result['challenge']->public_id, 'expires_at' => CarbonImmutable::parse((string) $result['challenge']->expires_at)->toIso8601String()]);
    }

    public function verifyOtp(Request $request, Tenant $tenant, VerifyPortalOtp $verifyOtp): JsonResponse
    {
        $validated = $request->validate(['challenge_id' => ['required', 'string', 'max:26'], 'code' => ['required', 'digits:6']]);
        $result = $verifyOtp->handle($tenant, $validated['challenge_id'], $validated['code'], $request->userAgent(), $request->ip());

        return response()->json($result);
    }

    public function requestCustomerOtp(Request $request, RequestPortalOtp $requestOtp): JsonResponse
    {
        $tenant = $request->attributes->get('portal_tenant');
        abort_unless($tenant instanceof Tenant, 400, 'A tenant context is required for customer authentication.');
        $validated = $request->validate(['phone' => ['required', 'string', 'max:40']]);
        $requestOtp->handle($tenant, $validated['phone'], $request->ip());

        return response()->json(['expires_in' => 300, 'resend_after' => 60]);
    }

    public function verifyCustomerOtp(Request $request, VerifyPortalOtp $verifyOtp): JsonResponse
    {
        $tenant = $request->attributes->get('portal_tenant');
        abort_unless($tenant instanceof Tenant, 400, 'A tenant context is required for customer authentication.');
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'code' => ['required', 'digits:6'],
            'device_id' => ['required', 'string', 'max:100'],
        ]);

        try {
            $result = $verifyOtp->handleByPhone($tenant, $validated['phone'], $validated['code'], $request->userAgent(), $request->ip(), $validated['device_id']);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([...$result, 'type' => 'Bearer']);
    }

    public function logout(Request $request, RevokePortalSession $revoke): JsonResponse
    {
        $session = $request->attributes->get('portal_session');
        abort_unless($session instanceof PortalSession, 401);
        $revoke->handle($session);

        return response()->json(status: 204);
    }
}
