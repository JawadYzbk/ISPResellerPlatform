<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Settlement;
use App\Models\User;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ApprovePartnerSettlement implements Action
{
    public function handle(Settlement $settlement, User $approver): Settlement
    {
        if ($settlement->tenant_id !== app(Tenancy::class)->requireId() || $approver->tenant_id !== $settlement->tenant_id) {
            throw new DomainException('Settlement and approver must belong to the current tenant.');
        }
        if (in_array($settlement->status, ['approved', 'paid'], true)) {
            return $settlement;
        }
        if ($settlement->status !== 'draft') {
            throw new DomainException('Only draft settlements can be approved.');
        }

        return DB::transaction(function () use ($settlement, $approver): Settlement {
            $locked = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if (in_array($locked->status, ['approved', 'paid'], true)) {
                return $locked;
            }
            if ($locked->status !== 'draft') {
                throw new DomainException('Only draft settlements can be approved.');
            }
            $locked->forceFill(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()])->save();

            return $locked->refresh();
        });
    }
}
