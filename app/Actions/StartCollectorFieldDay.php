<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorFieldDay;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class StartCollectorFieldDay implements Action
{
    public function handle(User $collector, float $latitude, float $longitude, ?int $accuracy): CollectorFieldDay
    {
        if ($collector->role !== 'collector') {
            throw new DomainException('Only collector accounts can start a field day.');
        }

        return DB::transaction(function () use ($collector, $latitude, $longitude, $accuracy): CollectorFieldDay {
            User::query()->lockForUpdate()->findOrFail($collector->id);
            if (CollectorFieldDay::query()->where('user_id', $collector->id)->whereNull('checked_out_at')->exists()) {
                throw new DomainException('Your field day is already active.');
            }

            return CollectorFieldDay::create([
                'user_id' => $collector->id,
                'checked_in_at' => now(),
                'check_in_latitude' => $latitude,
                'check_in_longitude' => $longitude,
                'check_in_accuracy_meters' => $accuracy,
                'check_in_source' => 'web_geolocation',
            ]);
        });
    }
}
