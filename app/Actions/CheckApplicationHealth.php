<?php

namespace App\Actions;

use App\Contracts\Action;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final readonly class CheckApplicationHealth implements Action
{
    /** @return array{status: string, checks: array<string, string>} */
    public function handle(): array
    {
        $checks = [];
        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'failed';
        }
        try {
            Cache::put('healthcheck', 'ok', 5);
            $checks['cache'] = Cache::get('healthcheck') === 'ok' ? 'ok' : 'failed';
        } catch (\Throwable) {
            $checks['cache'] = 'failed';
        }
        try {
            Queue::size('default');
            $checks['queue'] = 'ok';
        } catch (\Throwable) {
            $checks['queue'] = 'failed';
        }

        return ['status' => in_array('failed', $checks, true) ? 'degraded' : 'ok', 'checks' => $checks];
    }
}
