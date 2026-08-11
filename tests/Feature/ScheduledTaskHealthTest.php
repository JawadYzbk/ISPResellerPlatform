<?php

use App\Support\ScheduledTaskMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('records successful monitored task runs as healthy', function (): void {
    $at = CarbonImmutable::parse('2026-08-11 10:00:00', 'UTC');
    $monitor = app(ScheduledTaskMonitor::class);
    $monitor->markStarted($at->subMinute());
    $monitor->record('php artisan services:suspend-overdue', true, $at);

    expect($monitor->check($at)['status'])->toBe('ok')
        ->and($monitor->check($at)['checks']['scheduled_services_suspend_overdue'])->toBe('ok');
});

it('marks failed monitored task runs as actionable failures', function (): void {
    $at = CarbonImmutable::parse('2026-08-11 10:00:00', 'UTC');
    $monitor = app(ScheduledTaskMonitor::class);
    $monitor->markStarted($at->subMinute());
    $monitor->record('php artisan routers:reconcile-subscribers', false, $at);

    expect($monitor->check($at)['status'])->toBe('failed')
        ->and($monitor->check($at)['checks']['scheduled_routers_reconcile_subscribers'])->toBe('failed');
});

it('marks a task stale after its interval and grace window', function (): void {
    $at = CarbonImmutable::parse('2026-08-11 10:00:00', 'UTC');
    $monitor = app(ScheduledTaskMonitor::class);
    $monitor->markStarted($at->subHours(3));

    expect($monitor->check($at)['status'])->toBe('stale')
        ->and($monitor->check($at)['checks']['scheduled_services_suspend_overdue'])->toBe('stale');
});

it('does not create state for unmonitored commands', function (): void {
    $monitor = app(ScheduledTaskMonitor::class);
    $monitor->record('php artisan inspire', true);

    expect(Cache::get('platform:schedule-monitor:'.sha1('inspire')))->toBeNull();
});

it('records Laravel scheduler success events for monitored commands', function (): void {
    $task = new Event(app(CacheEventMutex::class), 'php artisan services:suspend-overdue');

    event(new ScheduledTaskFinished($task, 0.1));

    expect(Cache::get('platform:schedule-monitor:'.sha1('services:suspend-overdue'))['last_status'])->toBe('ok');
});
