<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\PortalOtpChallenge;
use App\Models\Tenant;
use App\Support\PhoneNormalizer;
use App\Support\PortalOtpVerification;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class VerifyPortalOtpByPhone implements Action
{
    public function __construct(
        private PhoneNormalizer $phones,
        private Tenancy $tenancy,
        private PortalOtpVerification $verification,
    ) {}

    /** @return array{token: string, expires_at: string} */
    public function handle(Tenant $tenant, string $phone, string $code, ?string $userAgent = null, ?string $ip = null, ?string $deviceId = null): array
    {
        return $this->tenancy->run($tenant, function () use ($phone, $code, $userAgent, $ip, $deviceId): array {
            try {
                $normalized = $this->phones->normalize($phone);
            } catch (\InvalidArgumentException) {
                throw new DomainException('The portal verification code is invalid or expired.');
            }

            return DB::transaction(fn (): array => $this->verification->handle(
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
}
