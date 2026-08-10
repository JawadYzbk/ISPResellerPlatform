<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\WorkOrder;

final readonly class GetWorkOrderDetails implements Action
{
    public function handle(WorkOrder $workOrder): WorkOrder
    {
        return $workOrder->load(['customer', 'service', 'assignee', 'events.actor', 'mediaUploads', 'signature.media', 'materials.item', 'materials.warehouse']);
    }
}
