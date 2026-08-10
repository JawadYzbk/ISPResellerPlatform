<?php

use App\Domain\Services\ServiceStateMachine;
use App\Enums\ServiceStatus;
use App\Events\ServiceStatusChanged;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
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

it('authenticates only the current tenant realtime channel', function (): void {
    $north = Tenant::create(['name' => 'Northline', 'slug' => 'northline-auth', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $south = Tenant::create(['name' => 'Southline', 'slug' => 'southline-auth', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $north->id, 'name' => 'Operator', 'email' => 'realtime@example.test', 'password' => 'password']);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($north);
    $user->assignRole('tenant_owner');
    expect($user->can('services.view'))->toBeTrue();
    $channel = Broadcast::getChannels()->get('tenant.{tenantPublicId}');
    expect($channel($user, $north->public_id))->toBeTrue();
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
    Broadcast::connection('reverb')->channel('tenant.{tenantPublicId}', $channel);

    $allowed = $this->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-tenant.'.$north->public_id,
    ]);
    $denied = $this->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-tenant.'.$south->public_id,
    ]);

    $allowed->assertOk();
    $denied->assertForbidden();
});
