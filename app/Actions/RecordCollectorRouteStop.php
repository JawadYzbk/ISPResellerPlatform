<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorFieldDay;
use App\Models\CollectorRoute;
use App\Models\CollectorRouteStop;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class RecordCollectorRouteStop implements Action
{
    public function handle(
        User $collector,
        CollectorRouteStop $stop,
        string $outcome,
        ?string $note,
        float $latitude,
        float $longitude,
        ?int $accuracy,
    ): CollectorRouteStop {
        if (! in_array($outcome, array_diff(CollectorRouteStop::OUTCOMES, ['pending']), true)) {
            throw new DomainException('Choose a valid visit outcome.');
        }

        $today = now($collector->tenant()->value('timezone') ?: 'UTC')->toDateString();

        return DB::transaction(function () use ($collector, $stop, $outcome, $note, $latitude, $longitude, $accuracy, $today): CollectorRouteStop {
            User::query()->lockForUpdate()->findOrFail($collector->id);
            $route = CollectorRoute::query()->lockForUpdate()->findOrFail($stop->collector_route_id);
            if ($route->user_id !== $collector->id) {
                throw new DomainException('This stop is not assigned to your route.');
            }
            if ($route->route_date->toDateString() !== $today) {
                throw new DomainException('Visit outcomes can only be recorded on today\'s route.');
            }
            if (! CollectorFieldDay::query()->where('user_id', $collector->id)->whereNull('checked_out_at')->exists()) {
                throw new DomainException('Start your field day before recording visit outcomes.');
            }

            $locked = CollectorRouteStop::query()->lockForUpdate()->findOrFail($stop->id);
            if ($locked->outcome !== 'pending') {
                throw new DomainException('This visit outcome has already been recorded.');
            }
            $locked->forceFill([
                'outcome' => $outcome,
                'note' => filled($note) ? trim((string) $note) : null,
                'visited_at' => now(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy_meters' => $accuracy,
            ])->save();

            if ($route->status === 'planned') {
                $route->forceFill(['status' => 'in_progress', 'started_at' => now()])->save();
            }
            if (! $route->stops()->where('outcome', 'pending')->exists()) {
                $route->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            }

            return $locked->refresh()->load('customer.zone');
        });
    }
}
