<?php

namespace App\Console\Commands;

use App\Actions\MonitorPlatform;
use Illuminate\Console\Command;
use Throwable;

final class MonitorPlatformCommand extends Command
{
    protected $signature = 'platform:monitor';

    protected $description = 'Check platform health and deliver deduplicated alert transitions.';

    public function handle(MonitorPlatform $monitor): int
    {
        try {
            $result = $monitor->handle();
        } catch (Throwable) {
            $this->error('Platform monitoring failed.');

            return self::FAILURE;
        }

        $this->line(sprintf('Platform health: %s; alert: %s.', $result['status'], $result['alert']));

        return $result['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
