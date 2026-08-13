<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorFieldDay;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class EndCollectorFieldDay implements Action
{
    public function handle(User $collector, float $latitude, float $longitude, ?int $accuracy): CollectorFieldDay
    {
        return DB::transaction(function () use ($collector, $latitude, $longitude, $accuracy): CollectorFieldDay {
            User::query()->lockForUpdate()->findOrFail($collector->id);
            $fieldDay = CollectorFieldDay::query()
                ->where('user_id', $collector->id)
                ->whereNull('checked_out_at')
                ->lockForUpdate()
                ->latest('checked_in_at')
                ->first();
            if (! $fieldDay instanceof CollectorFieldDay) {
                throw new DomainException('No active field day is available to end.');
            }

            $fieldDay->forceFill([
                'checked_out_at' => now(),
                'check_out_latitude' => $latitude,
                'check_out_longitude' => $longitude,
                'check_out_accuracy_meters' => $accuracy,
                'check_out_source' => 'web_geolocation',
            ])->save();

            return $fieldDay->refresh();
        });
    }
}
