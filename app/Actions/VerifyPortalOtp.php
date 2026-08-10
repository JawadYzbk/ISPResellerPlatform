<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\PortalOtpChallenge;
use App\Models\Tenant;
use App\Support\PortalOtpVerification;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

final readonly class VerifyPortalOtp implements Action
{
    public function __construct(private PortalOtpVerification $verification) {}

    /** @return array{token: string, expires_at: string} */
    public function handle(Tenant $tenant, string $challengeId, string $code, ?string $userAgent = null, ?string $ip = null, ?string $deviceId = null): array
    {
        return app(Tenancy::class)->run($tenant, fn (): array => DB::transaction(fn (): array => $this->verification->handle(
            PortalOtpChallenge::query()->lockForUpdate()->where('public_id', $challengeId)->first(),
            $code,
            $userAgent,
            $ip,
            $deviceId,
        )));
    }
}
