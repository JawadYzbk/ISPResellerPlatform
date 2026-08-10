<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\PortalOtpChallenge;
use App\Models\Tenant;
use App\Support\PhoneNormalizer;
use App\Support\Tenancy;

final readonly class RequestPortalOtp implements Action
{
    /** @return array{challenge: PortalOtpChallenge, code: string} */
    public function handle(Tenant $tenant, string $phone, ?string $ip = null): array
    {
        return app(Tenancy::class)->run($tenant, function () use ($phone, $ip): array {
            $normalized = null;
            try {
                $normalized = app(PhoneNormalizer::class)->normalize($phone);
            } catch (\InvalidArgumentException) {
                // Invalid and unknown numbers intentionally follow the same external response path.
            }
            $customer = $normalized === null ? null : Customer::query()->where('phone_normalized', $normalized)->first();
            $code = (string) random_int(100000, 999999);
            $challenge = PortalOtpChallenge::create([
                'customer_id' => $customer?->id,
                'phone_normalized' => $normalized,
                'phone_hash' => hash('sha256', $normalized ?? $phone),
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes(5),
                'request_ip' => $ip,
            ]);

            return ['challenge' => $challenge, 'code' => $code];
        });
    }
}
