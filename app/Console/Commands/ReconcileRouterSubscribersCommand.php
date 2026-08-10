<?php

namespace App\Console\Commands;

use App\Actions\ReconcileRouterSubscribers;
use App\Models\Router;
use Illuminate\Console\Command;

final class ReconcileRouterSubscribersCommand extends Command
{
    protected $signature = 'routers:reconcile-subscribers';

    protected $description = 'Compare RouterOS PPP subscribers with platform services without mutating either side.';

    public function handle(ReconcileRouterSubscribers $reconcile): int
    {
        $failed = false;
        Router::query()->each(function (Router $router) use ($reconcile, &$failed): void {
            $result = $reconcile->handle($router);
            $this->line($router->name.': '.$result['status'].' ('.count($result['platform_only']).' platform-only, '.count($result['router_only']).' router-only)');
            $failed = $failed || $result['status'] !== 'in_sync';
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
