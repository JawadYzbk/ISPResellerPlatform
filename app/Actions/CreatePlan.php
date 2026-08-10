<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Support\Facades\DB;

final readonly class CreatePlan implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Plan
    {
        return DB::transaction(function () use ($data): Plan {
            $plan = Plan::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'download_kbps' => $data['download_kbps'],
                'upload_kbps' => $data['upload_kbps'],
                'duration_days' => $data['duration_days'],
                'amount_minor' => $data['amount_minor'],
                'currency' => $data['currency'],
                'status' => $data['status'],
            ]);
            PlanPrice::create([
                'plan_id' => $plan->id,
                'currency' => $data['currency'],
                'amount_minor' => $data['amount_minor'],
                'effective_from' => $data['effective_from'],
            ]);

            return $plan->refresh();
        });
    }
}
