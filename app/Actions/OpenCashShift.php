<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\CashShiftStatus;
use App\Models\CashShift;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class OpenCashShift implements Action
{
    /** @param array<string, int> $openingFloat */
    public function handle(User $user, array $openingFloat = []): CashShift
    {
        $normalizedFloat = [];
        foreach ($openingFloat as $currency => $amount) {
            $currency = strtoupper(trim((string) $currency));
            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || ! is_int($amount) || $amount < 0) {
                throw new DomainException('Opening float must contain non-negative integer amounts keyed by ISO currency.');
            }
            $normalizedFloat[$currency] = $amount;
        }
        ksort($normalizedFloat);

        return DB::transaction(function () use ($user, $normalizedFloat): CashShift {
            User::query()->lockForUpdate()->findOrFail($user->id);
            if (CashShift::query()->where('user_id', $user->id)->where('status', CashShiftStatus::Open)->exists()) {
                throw new DomainException('The cashier already has an open shift.');
            }

            return CashShift::create([
                'user_id' => $user->id,
                'status' => CashShiftStatus::Open,
                'opened_at' => now(),
                'opening_float' => $normalizedFloat === [] ? null : $normalizedFloat,
            ]);
        });
    }
}
