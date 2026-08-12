<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CommissionRule;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class SavePartnerPriceBookItem implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Partner $partner, Plan $plan, array $data): PriceBookItem
    {
        if ($partner->tenant_id !== $plan->tenant_id || $partner->tenant_id !== app(Tenancy::class)->requireId()) {
            throw new DomainException('Partner and plan must belong to the current tenant.');
        }

        $currency = strtoupper((string) $data['currency']);
        if ($currency !== $partner->currency) {
            throw new DomainException('A partner price book must use the partner wallet currency.');
        }

        $effectiveFrom = CarbonImmutable::parse((string) $data['effective_from'])->startOfDay();

        return DB::transaction(function () use ($partner, $plan, $data, $currency, $effectiveFrom): PriceBookItem {
            $book = PriceBook::query()
                ->where('partner_id', $partner->id)
                ->where('name', 'Partner prices')
                ->lockForUpdate()
                ->first();

            if (! $book instanceof PriceBook) {
                $book = PriceBook::create([
                    'partner_id' => $partner->id,
                    'name' => 'Partner prices',
                    'status' => 'active',
                    'effective_from' => $effectiveFrom,
                ]);
            } elseif ($book->effective_from->greaterThan($effectiveFrom)) {
                $book->update(['effective_from' => $effectiveFrom]);
            }

            $next = PriceBookItem::query()
                ->where('price_book_id', $book->id)
                ->where('plan_id', $plan->id)
                ->where('currency', $currency)
                ->where('effective_from', '>', $effectiveFrom)
                ->orderBy('effective_from')
                ->lockForUpdate()
                ->first();
            $previous = PriceBookItem::query()
                ->where('price_book_id', $book->id)
                ->where('plan_id', $plan->id)
                ->where('currency', $currency)
                ->where('effective_from', '<', $effectiveFrom)
                ->orderByDesc('effective_from')
                ->lockForUpdate()
                ->first();
            $existing = PriceBookItem::query()
                ->where('price_book_id', $book->id)
                ->where('plan_id', $plan->id)
                ->where('currency', $currency)
                ->where('effective_from', $effectiveFrom)
                ->lockForUpdate()
                ->first();

            $previous?->update(['effective_to' => $effectiveFrom]);
            $rule = $existing instanceof PriceBookItem ? $existing->commissionRule : null;
            if (! $rule instanceof CommissionRule) {
                $rule = CommissionRule::create([
                    'partner_id' => $partner->id,
                    'type' => 'margin',
                    'value' => 0,
                    'plan_id' => $plan->id,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => $next?->effective_from,
                    'version' => ((int) CommissionRule::query()->where('partner_id', $partner->id)->where('plan_id', $plan->id)->max('version')) + 1,
                    'status' => 'active',
                ]);
            } else {
                $rule->update(['effective_to' => $next?->effective_from]);
            }

            $attributes = [
                'price_book_id' => $book->id,
                'plan_id' => $plan->id,
                'commission_rule_id' => $rule->id,
                'currency' => $currency,
                'buy_amount_minor' => (int) $data['buy_amount_minor'],
                'sell_amount_minor' => (int) $data['sell_amount_minor'],
                'min_amount_minor' => $data['min_amount_minor'] ?? null,
                'max_amount_minor' => $data['max_amount_minor'] ?? null,
                'effective_from' => $effectiveFrom,
                'effective_to' => $next?->effective_from,
            ];

            if ($existing instanceof PriceBookItem) {
                $existing->update($attributes);

                return $existing->refresh();
            }

            return PriceBookItem::create($attributes);
        });
    }
}
