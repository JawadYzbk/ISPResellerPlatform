<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\PlanUsageRate;

final readonly class ArchivePlanUsageRate implements Action
{
    public function handle(PlanUsageRate $rate): PlanUsageRate
    {
        $rate->forceFill(['status' => 'inactive'])->save();

        return $rate->refresh();
    }
}
