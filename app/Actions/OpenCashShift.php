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
    public function handle(User $user): CashShift
    {
        if (CashShift::query()->where('user_id', $user->id)->where('status', CashShiftStatus::Open)->exists()) {
            throw new DomainException('The cashier already has an open shift.');
        }

        return DB::transaction(fn (): CashShift => CashShift::create(['user_id' => $user->id, 'status' => CashShiftStatus::Open, 'opened_at' => now()]));
    }
}
