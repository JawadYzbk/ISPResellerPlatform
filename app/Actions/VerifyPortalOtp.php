<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\PortalOtpChallenge;
use App\Models\PortalSession;
use App\Models\Tenant;
use App\Support\PhoneNormalizer;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class VerifyPortalOtp implements Action
{
    /** @return array{token: string, expires_at: string} */
    public function handle(Tenant $tenant, string $challengeId, string $code, ?string $userAgent = null, ?string $ip = null, ?string $deviceId = null): array
    {
        return app(Tenancy::class)->run($tenant, fn (): array => DB::transaction(fn (): array => $this->issueSession(
            PortalOtpChallenge::query()->lockForUpdate()->where('public_id', $challengeId)->first(),
            $code,
            $userAgent,
            $ip,
            $deviceId,
        )));
    }

    /** @return array{token: string, expires_at: string} */
    public function handleByPhone(Tenant $tenant, string $phone, string $code, ?string $userAgent = null, ?string $ip = null, ?string $deviceId = null): array
    {
        return app(Tenancy::class)->run($tenant, function () use ($phone, $code, $userAgent, $ip, $deviceId): array {
            try {
                $normalized = app(PhoneNormalizer::class)->normalize($phone);
            } catch (\InvalidArgumentException) {
                throw new DomainException('The portal verification code is invalid or expired.');
            }

            return DB::transaction(fn (): array => $this->issueSession(
                PortalOtpChallenge::query()
                    ->lockForUpdate()
                    ->where('phone_hash', hash('sha256', $normalized))
                    ->whereNull('consumed_at')
                    ->where('expires_at', '>', now())
                    ->orderByDesc('id')
                    ->first(),
                $code,
                $userAgent,
                $ip,
                $deviceId,
            ));
        });
    }

    /** @return array{token: string, expires_at: string} */
    private function issueSession(?PortalOtpChallenge $challenge, string $code, ?string $userAgent, ?string $ip, ?string $deviceId): array
    {
        if ($challenge === null || $challenge->consumed_at !== null || CarbonImmutable::parse((string) $challenge->expires_at)->isPast() || $challenge->attempts >= 5) {
            throw new DomainException('The portal verification code is invalid or expired.');
        }
        $challenge->increment('attempts');
        if (! hash_equals($challenge->code_hash, hash('sha256', $code)) || $challenge->customer_id === null) {
            throw new DomainException('The portal verification code is invalid or expired.');
        }

        $plainToken = 'portal_'.Str::random(64);
        $session = PortalSession::create(['customer_id' => $challenge->customer_id, 'device_id' => $deviceId, 'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDays(30), 'user_agent' => $userAgent, 'ip_address' => $ip]);
        $challenge->forceFill(['consumed_at' => now()])->save();

        return ['token' => $plainToken, 'expires_at' => CarbonImmutable::parse((string) $session->expires_at)->toIso8601String()];
    }
}
