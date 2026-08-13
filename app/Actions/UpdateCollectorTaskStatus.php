<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorTask;
use App\Models\User;
use App\Support\CollectorTaskAccess;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCollectorTaskStatus implements Action
{
    public function __construct(private CollectorTaskAccess $access) {}

    public function handle(User $actor, CollectorTask $task, string $status): CollectorTask
    {
        if (! $this->access->canView($actor, $task)) {
            throw new DomainException('This task is not assigned to you.');
        }
        if (! in_array($status, CollectorTask::STATUSES, true)) {
            throw new DomainException('Choose a valid task status.');
        }

        return DB::transaction(function () use ($actor, $task, $status): CollectorTask {
            $locked = CollectorTask::query()->lockForUpdate()->findOrFail($task->id);
            if ($this->access->isManager($actor)) {
                if ($locked->status === $status) {
                    return $locked;
                }
            } else {
                $next = [
                    'assigned' => 'acknowledged',
                    'acknowledged' => 'in_progress',
                    'in_progress' => 'completed',
                ][$locked->status] ?? null;
                if ($next !== $status) {
                    throw new DomainException('Complete the task workflow in order.');
                }
            }

            $locked->forceFill([
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
            ])->save();

            return $locked->refresh();
        });
    }
}
