<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

final readonly class RollupDailyUsage implements Action
{
    public function handle(Tenant $tenant, CarbonImmutable $date): int
    {
        return app(Tenancy::class)->run($tenant, function () use ($date): int {
            $rows = CurrentSession::query()
                ->selectRaw('service_id, SUM(input_octets) as input_octets, SUM(output_octets) as output_octets')
                ->whereDate('acct_start_time', $date->toDateString())
                ->groupBy('service_id')
                ->get();
            foreach ($rows as $row) {
                $input = (int) $row->input_octets;
                $output = (int) $row->output_octets;
                $usage = UsageDaily::query()
                    ->where('service_id', $row->service_id)
                    ->whereDate('usage_date', $date->toDateString())
                    ->first();
                $attributes = ['input_octets' => $input, 'output_octets' => $output, 'total_octets' => $input + $output, 'rolled_up_at' => now()];
                if ($usage === null) {
                    UsageDaily::create(['service_id' => $row->service_id, 'usage_date' => $date->toDateString(), ...$attributes]);
                } else {
                    $usage->forceFill($attributes)->save();
                }
            }

            return $rows->count();
        });
    }
}
