<?php

namespace App\Security;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OTPHP\TOTP;

final class TwoFactorService
{
    /** @return array{secret: string, provisioning_uri: string, recovery_codes: list<string>} */
    public function begin(User $user): array
    {
        $totp = TOTP::create()->withLabel($user->email)->withIssuer(config('app.name', 'ISP Platform'));
        $secret = $totp->getSecret();
        $recoveryCodes = collect(range(1, 8))->map(fn (): string => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)))->all();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $recoveryCodes),
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'provisioning_uri' => $totp->getProvisioningUri(),
            'recovery_codes' => $recoveryCodes,
        ];
    }

    public function confirm(User $user, string $code): bool
    {
        if (! $this->verifyTotp($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    public function verify(User $user, string $code): bool
    {
        if (! $this->enabled($user)) {
            return false;
        }

        if ($this->verifyTotp($user, $code)) {
            return true;
        }

        /** @var list<string> $recoveryCodes */
        $recoveryCodes = $user->two_factor_recovery_codes ?? [];
        foreach ($recoveryCodes as $index => $recoveryCode) {
            if (! Hash::check($code, $recoveryCode)) {
                continue;
            }

            unset($recoveryCodes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();

            return true;
        }

        return false;
    }

    public function enabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null;
    }

    private function verifyTotp(User $user, string $code): bool
    {
        if (! is_string($user->two_factor_secret) || $user->two_factor_secret === '') {
            return false;
        }

        return TOTP::createFromSecret($user->two_factor_secret)->verify($code, leeway: 15);
    }
}
