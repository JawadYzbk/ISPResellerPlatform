<?php

use App\Domain\Services\ServiceStateMachine;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records guarded status transitions as service events', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create();
    $service = Service::factory()->for($customer)->for($plan)->create(['status' => ServiceStatus::Pending]);

    $updated = app(ServiceStateMachine::class)->transition($service, ServiceStatus::Active);

    expect($updated->status)->toBe(ServiceStatus::Active)
        ->and(ServiceEvent::where('service_id', $service->id)->first()->from_status)->toBe('pending');
});

it('requires an explicit reactivation for terminated services', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['status' => ServiceStatus::Terminated]);

    expect(fn (): Service => app(ServiceStateMachine::class)->transition($service, ServiceStatus::Active))
        ->toThrow(DomainException::class);

    expect(app(ServiceStateMachine::class)->transition($service, ServiceStatus::Active, explicitReactivation: true)->status)
        ->toBe(ServiceStatus::Active);
});
