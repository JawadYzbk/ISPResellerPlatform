<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Partner;
use App\Support\Tenancy;
use DomainException;

final readonly class CreatePartner implements Action
{
    public function handle(string $name, string $code, string $currency, ?Partner $parent = null, int $creditLimit = 0, int $lowBalanceThreshold = 0): Partner
    {
        if ($parent !== null && $parent->tenant_id !== app(Tenancy::class)->requireId()) {
            throw new DomainException('A partner parent must belong to the current tenant.');
        }

        return Partner::create(['name' => $name, 'code' => $code, 'currency' => $currency, 'parent_id' => $parent?->id, 'path' => '/', 'credit_limit' => max(0, $creditLimit), 'low_balance_threshold' => max(0, $lowBalanceThreshold)]);
    }
}
