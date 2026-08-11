<?php

namespace App\Listeners;

use App\Support\ScheduledTaskMonitor;
use Illuminate\Console\Events\ScheduledTaskFinished;

final readonly class RecordScheduledTaskFinished
{
    public function __construct(private ScheduledTaskMonitor $monitor) {}

    public function handle(ScheduledTaskFinished $event): void
    {
        $this->monitor->record((string) $event->task->command, true);
    }
}
