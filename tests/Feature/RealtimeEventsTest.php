<?php

use App\Domain\Services\ServiceStateMachine;
use App\Enums\ServiceStatus;
use App\Events\ServiceStatusChanged;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('broadcasts service status changes only after the state transaction commits', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['status' => ServiceStatus::Pending]);
    Event::fake([ServiceStatusChanged::class]);

    $changed = app(ServiceStateMachine::class)->transition($service, ServiceStatus::Active);
    $event = null;
    Event::assertDispatched(ServiceStatusChanged::class, function (ServiceStatusChanged $dispatched) use (&$event, $changed): bool {
        $event = $dispatched;

        return $dispatched->serviceId === $changed->public_id;
    });

    expect($event)->toBeInstanceOf(ServiceStatusChanged::class)
        ->and($event->broadcastAs())->toBe('service.status.changed')
        ->and($event->tenantPublicId)->toBe($tenant->public_id)
        ->and($event->broadcastOn()[0]->name)->toBe('private-tenant.'.$tenant->public_id);
});
