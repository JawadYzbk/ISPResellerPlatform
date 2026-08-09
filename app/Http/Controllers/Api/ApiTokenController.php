<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class ApiTokenController extends Controller
{
    public function store(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'otp' => ['nullable', 'string', 'min:6', 'max:16'],
        ]);
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user instanceof User || ! Hash::check($validated['password'], $user->password)) {
            abort(401, 'Invalid credentials.');
        }

        if ($user->requiresTwoFactor() && (! $twoFactor->enabled($user) || ! $twoFactor->verify($user, (string) ($validated['otp'] ?? '')))) {
            abort(423, 'A valid two-factor code is required to issue an API token.');
        }

        $token = $user->createToken($validated['device_name']);

        return response()->json(['token' => $token->plainTextToken, 'type' => 'Bearer']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }
}
