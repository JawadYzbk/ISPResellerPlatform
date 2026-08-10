<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Promotion;

final readonly class ArchivePromotion implements Action
{
    public function handle(Promotion $promotion): Promotion
    {
        $promotion->forceFill(['is_active' => false])->save();

        return $promotion->refresh();
    }
}
