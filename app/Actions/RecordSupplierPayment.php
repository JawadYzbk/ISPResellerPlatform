<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RecordSupplierPayment implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(SupplierBill $bill, User $actor, array $data): SupplierPayment
    {
        return DB::transaction(function () use ($bill, $actor, $data): SupplierPayment {
            $lockedBill = SupplierBill::query()->lockForUpdate()->findOrFail($bill->id);
            $paid = (int) SupplierPayment::query()->where('supplier_bill_id', $lockedBill->id)->sum('amount');
            $amount = (int) $data['amount'];
            $remaining = (int) $lockedBill->amount - $paid;

            if ($amount > $remaining) {
                throw new InvalidArgumentException('The supplier payment exceeds the bill balance.');
            }

            $payment = $lockedBill->payments()->create([
                'amount' => $amount,
                'currency' => $lockedBill->currency,
                'paid_at' => $data['paid_at'],
                'method' => trim((string) $data['method']),
                'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'actor_id' => $actor->id,
            ]);
            $lockedBill->forceFill(['status' => $amount + $paid >= (int) $lockedBill->amount ? 'paid' : 'open'])->save();

            return $payment;
        });
    }
}
