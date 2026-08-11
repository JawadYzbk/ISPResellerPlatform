<?php

namespace App\Listeners;

use App\Support\ScheduledTaskMonitor;
use Illuminate\Console\Events\ScheduledTaskFailed;

final readonly class RecordScheduledTaskFailed
{
    public function __construct(private ScheduledTaskMonitor $monitor) {}

    public function handle(ScheduledTaskFailed $event): void
    {
        $this->monitor->record((string) $event->task->command, false);
    }
}
