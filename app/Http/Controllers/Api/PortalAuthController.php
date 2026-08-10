<?php

namespace App\Http\Controllers\Api;

use App\Actions\RequestPortalOtp;
use App\Actions\VerifyPortalOtp;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PortalAuthController extends Controller
{
    public function requestOtp(Request $request, Tenant $tenant, RequestPortalOtp $requestOtp): JsonResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:40']]);
        $result = $requestOtp->handle($tenant, $validated['phone'], $request->ip());

        return response()->json(['challenge_id' => $result['challenge']->id, 'expires_at' => CarbonImmutable::parse((string) $result['challenge']->expires_at)->toIso8601String()]);
    }

    public function verifyOtp(Request $request, Tenant $tenant, VerifyPortalOtp $verifyOtp): JsonResponse
    {
        $validated = $request->validate(['challenge_id' => ['required', 'integer'], 'code' => ['required', 'digits:6']]);
        $result = $verifyOtp->handle($tenant, $validated['challenge_id'], $validated['code'], $request->userAgent(), $request->ip());

        return response()->json($result);
    }
}
