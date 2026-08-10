<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\PriceBookItem;
use DomainException;

final readonly class CalculatePartnerCommission implements Action
{
    public function handle(PriceBookItem $item): int
    {
        if ($item->max_amount_minor !== null && $item->sell_amount_minor > $item->max_amount_minor) {
            throw new DomainException('The price book sell amount exceeds its configured maximum.');
        }
        if ($item->min_amount_minor !== null && $item->sell_amount_minor < $item->min_amount_minor) {
            throw new DomainException('The price book sell amount is below its configured minimum.');
        }

        $rule = $item->commissionRule;
        if ($rule === null) {
            return 0;
        }

        return match ($rule->type) {
            'margin' => $item->sell_amount_minor - $item->buy_amount_minor,
            'percent' => intdiv($item->sell_amount_minor * $rule->value, 10_000),
            'fixed' => $rule->value,
            default => throw new DomainException('Unsupported commission rule type.'),
        };
    }
}
