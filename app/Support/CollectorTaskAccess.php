<?php

namespace App\Support;

use App\Models\CollectorTask;
use App\Models\User;

final class CollectorTaskAccess
{
    public function canView(User $user, CollectorTask $task): bool
    {
        return (int) $user->tenant_id === (int) $task->tenant_id
            && ($user->can('reports.operations') || (int) $task->collector_id === (int) $user->id);
    }

    public function isManager(User $user): bool
    {
        return $user->can('reports.operations');
    }
}
