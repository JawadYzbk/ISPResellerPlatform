<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorTask;
use App\Models\CollectorTaskRead;
use App\Models\User;
use App\Support\CollectorTaskAccess;
use DomainException;

final readonly class MarkCollectorTaskRead implements Action
{
    public function __construct(private CollectorTaskAccess $access) {}

    public function handle(User $actor, CollectorTask $task): CollectorTaskRead
    {
        if (! $this->access->canView($actor, $task)) {
            throw new DomainException('This task is not available to you.');
        }

        return CollectorTaskRead::query()->updateOrCreate(
            ['collector_task_id' => $task->id, 'user_id' => $actor->id],
            ['tenant_id' => $task->tenant_id, 'last_read_at' => now()],
        );
    }
}
