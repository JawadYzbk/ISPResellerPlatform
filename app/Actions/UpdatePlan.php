<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Plan;
use App\Models\PlanPrice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpdatePlan implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $data): Plan {
            $locked = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            $effectiveFrom = CarbonImmutable::parse((string) $data['effective_from']);
            $currency = strtoupper((string) $data['currency']);

            $locked->forceFill([
                'name' => $data['name'],
                'slug' => Str::slug((string) ($data['slug'] ?: $data['name'])),
                'download_kbps' => $data['download_kbps'],
                'upload_kbps' => $data['upload_kbps'],
                'duration_days' => $data['duration_days'],
                'amount_minor' => $data['amount_minor'],
                'currency' => $currency,
                'status' => $data['status'],
            ])->save();

            PlanPrice::query()
                ->where('plan_id', $locked->id)
                ->where('currency', $currency)
                ->where('effective_from', '<', $effectiveFrom)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $effectiveFrom))
                ->update(['effective_to' => $effectiveFrom]);

            PlanPrice::updateOrCreate(
                ['plan_id' => $locked->id, 'currency' => $currency, 'effective_from' => $effectiveFrom],
                ['amount_minor' => $data['amount_minor'], 'effective_to' => null],
            );

            return $locked->refresh();
        });
    }
}
