<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Partner;

final readonly class UpdatePartner implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Partner $partner, array $data): Partner
    {
        $partner->update([
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'credit_limit' => max(0, (int) $data['credit_limit']),
            'low_balance_threshold' => max(0, (int) $data['low_balance_threshold']),
            'status' => $data['status'],
        ]);

        return $partner->refresh();
    }
}
