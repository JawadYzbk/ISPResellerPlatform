<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use App\Models\User;
use App\Security\ApiTokenAbilities;
use App\Security\TwoFactorService;
use App\Support\Api\UserApiResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class ApiTokenController extends Controller
{
    public function staffLogin(Request $request, TwoFactorService $twoFactor, ApiTokenAbilities $abilities, UserApiResource $resource): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'device_id' => ['required', 'string', 'max:100'],
            'abilities' => ['sometimes', 'array', 'min:1', 'max:3'],
            'abilities.*' => ['string', 'in:customer,staff:collector,staff:technician,staff:operator'],
        ]);
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user instanceof User || $user->role === 'customer' || ! Hash::check($validated['password'], $user->password)) {
            abort(401, 'Invalid staff credentials.');
        }

        $requestedAbilities = array_values($validated['abilities'] ?? []);
        if ($user->requiresTwoFactor()) {
            if (! $twoFactor->enabled($user)) {
                abort(423, 'Two-factor authentication must be configured before issuing a staff token.');
            }

            $challengeId = (string) Str::ulid();
            Cache::put('auth:staff:challenge:'.$challengeId, [
                'user_id' => $user->id,
                'device_name' => $validated['device_name'],
                'device_id' => $validated['device_id'],
                'abilities' => $requestedAbilities,
            ], now()->addMinutes(5));

            return response()->json([
                'two_factor_required' => true,
                'challenge_id' => $challengeId,
                'expires_in' => 300,
            ], 422);
        }

        return $this->issueStaffToken($user, $validated['device_name'], $requestedAbilities, $abilities, $resource, (string) $validated['device_id']);
    }

    public function staffTwoFactor(Request $request, TwoFactorService $twoFactor, ApiTokenAbilities $abilities, UserApiResource $resource): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => ['required', 'string', 'max:26'],
            'code' => ['required', 'string', 'min:6', 'max:16'],
        ]);
        $challenge = Cache::get('auth:staff:challenge:'.$validated['challenge_id']);
        abort_unless(is_array($challenge) && isset($challenge['user_id'], $challenge['device_name'], $challenge['device_id'], $challenge['abilities']), 422, 'The staff authentication challenge is invalid or expired.');
        $user = User::query()->find((int) $challenge['user_id']);
        abort_unless($user instanceof User && $user->role !== 'customer' && $twoFactor->verify($user, $validated['code']), 422, 'The two-factor code is invalid or expired.');
        Cache::forget('auth:staff:challenge:'.$validated['challenge_id']);

        /** @var list<string> $requestedAbilities */
        $requestedAbilities = array_values($challenge['abilities']);

        return $this->issueStaffToken($user, (string) $challenge['device_name'], $requestedAbilities, $abilities, $resource, (string) $challenge['device_id']);
    }

    public function store(Request $request, TwoFactorService $twoFactor, ApiTokenAbilities $abilities): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'otp' => ['nullable', 'string', 'min:6', 'max:16'],
            'abilities' => ['sometimes', 'array', 'min:1', 'max:3'],
            'abilities.*' => ['string', 'in:customer,staff:collector,staff:technician,staff:operator'],
        ]);
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user instanceof User || ! Hash::check($validated['password'], $user->password)) {
            abort(401, 'Invalid credentials.');
        }

        if ($user->requiresTwoFactor() && (! $twoFactor->enabled($user) || ! $twoFactor->verify($user, (string) ($validated['otp'] ?? '')))) {
            abort(423, 'A valid two-factor code is required to issue an API token.');
        }

        try {
            $tokenAbilities = $abilities->resolve($user, array_values($validated['abilities'] ?? []));
        } catch (DomainException $exception) {
            abort(403, $exception->getMessage());
        }
        $user->forceFill(['last_authenticated_at' => now()])->save();
        $token = $user->createToken($validated['device_name'], $tokenAbilities);

        return response()->json(['token' => $token->plainTextToken, 'type' => 'Bearer', 'abilities' => $tokenAbilities]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $bearerToken = $request->bearerToken();
        if (is_string($bearerToken) && $bearerToken !== '') {
            PersonalAccessToken::findToken($bearerToken)?->delete();
        } else {
            $request->user()?->currentAccessToken()?->delete();
        }

        return response()->json(status: 204);
    }

    public function me(Request $request, UserApiResource $resource): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $token = $user->currentAccessToken();

        return response()->json([
            ...$resource->make($user),
            'abilities' => $token->abilities,
        ]);
    }

    public function revokeDevice(Request $request, string $device): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('device_id', $device)
            ->delete();

        return response()->json(status: 204);
    }

    public function pushToken(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->tenant_id !== null, 401);
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'app' => ['required', 'string', 'max:100'],
        ]);
        $tokenHash = hash('sha256', $validated['token']);

        PushToken::query()->updateOrCreate(
            ['token_hash' => $tokenHash],
            [
                'user_id' => $user->id,
                'token_encrypted' => $validated['token'],
                'platform' => $validated['platform'],
                'app' => $validated['app'],
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return response()->json(status: 204);
    }

    /** @param list<string> $requestedAbilities */
    private function issueStaffToken(User $user, string $deviceName, array $requestedAbilities, ApiTokenAbilities $abilities, UserApiResource $resource, ?string $deviceId = null): JsonResponse
    {
        try {
            $tokenAbilities = $abilities->resolve($user, $requestedAbilities);
        } catch (DomainException $exception) {
            abort(403, $exception->getMessage());
        }
        $user->forceFill(['last_authenticated_at' => now()])->save();
        $token = $user->createToken($deviceName, $tokenAbilities);
        if ($deviceId !== null) {
            $token->accessToken->forceFill(['device_id' => $deviceId])->save();
        }

        return response()->json([
            'token' => $token->plainTextToken,
            'type' => 'Bearer',
            'abilities' => $tokenAbilities,
            'user' => $resource->make($user),
        ]);
    }
}
