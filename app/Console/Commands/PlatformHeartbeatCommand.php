<?php

namespace App\Console\Commands;

use App\Jobs\RecordQueueWorkerHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class PlatformHeartbeatCommand extends Command
{
    protected $signature = 'platform:heartbeat';

    protected $description = 'Record the scheduler heartbeat and enqueue a queue-worker heartbeat.';

    public function handle(): int
    {
        Cache::put('scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(5));
        RecordQueueWorkerHeartbeat::dispatch();
        $this->info('Platform heartbeat recorded.');

        return self::SUCCESS;
    }
}
