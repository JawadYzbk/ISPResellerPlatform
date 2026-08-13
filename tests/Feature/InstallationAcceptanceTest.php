<?php

use App\Enums\ServiceStatus;
use App\Enums\WorkOrderStatus;
use App\Models\DistributionBox;
use App\Models\NetworkBuilding;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('records installation evidence, requires acceptance, and activates the service on completion', function (): void {
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);
    app(Tenancy::class)->set($tenant);
    $operator = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Operations manager',
        'email' => 'installation-acceptance@example.test',
        'password' => Hash::make('password'),
        'role' => 'operations_manager',
    ]);
    app(CapabilitySeeder::class)->run();
    $operator->assignRole('operations_manager');
    $operator->forceFill(['last_authenticated_at' => now()])->save();

    $building = NetworkBuilding::create([
        'name' => 'Cedar Residence',
        'code' => 'CEDAR-01',
        'status' => 'active',
    ]);
    $box = DistributionBox::create([
        'network_building_id' => $building->id,
        'name' => 'Cedar Cabinet',
        'code' => 'CEDAR-CAB-01',
        'box_type' => 'cabinet',
        'capacity_ports' => 8,
        'status' => 'active',
    ]);
    $service = Service::factory()->create(['status' => ServiceStatus::Pending]);
    $workOrder = WorkOrder::create([
        'number' => 'WO-INSTALL-001',
        'type' => 'installation',
        'customer_id' => $service->customer_id,
        'service_id' => $service->id,
        'assigned_to' => $operator->id,
        'status' => WorkOrderStatus::Assigned,
        'metadata' => ['requires_installation_acceptance' => true],
    ]);

    $this->actingAs($operator)
        ->post(route('operations.work-orders.installation.save', $workOrder->public_id), [
            'network_building_id' => $building->id,
            'distribution_box_id' => $box->public_id,
            'network_port' => 3,
            'onu_serial' => 'ONU-CEDAR-003',
            'survey' => [
                'unit_label' => 'Apt 301',
                'access_notes' => 'Call the caretaker',
                'cable_route' => 'East riser',
                'power_available' => 'yes',
            ],
        ])
        ->assertRedirect(route('operations.work-orders.show', $workOrder->public_id))
        ->assertSessionHas('success', 'Installation details saved.');

    app(Tenancy::class)->set($tenant);
    expect($workOrder->refresh()->network_building_id)->toBe($building->id)
        ->and($workOrder->distribution_box_id)->toBe($box->id)
        ->and($workOrder->network_port)->toBe(3)
        ->and($workOrder->onu_serial)->toBe('ONU-CEDAR-003')
        ->and($workOrder->installation_survey['unit_label'])->toBe('Apt 301')
        ->and(WorkOrderEvent::query()->where('work_order_id', $workOrder->id)->where('event_type', 'installation_saved')->exists())->toBeTrue();

    $this->actingAs($operator)
        ->post(route('operations.work-orders.complete', $workOrder->public_id))
        ->assertSessionHasErrors('completion');

    app(Tenancy::class)->set($tenant);
    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Assigned);

    $this->actingAs($operator)
        ->post(route('operations.work-orders.activation.accept', $workOrder->public_id), [
            'note' => 'Customer confirmed service handover.',
        ])
        ->assertRedirect(route('operations.work-orders.show', $workOrder->public_id))
        ->assertSessionHas('success', 'Activation accepted.');

    app(Tenancy::class)->set($tenant);
    expect($workOrder->refresh()->activation_accepted_by_id)->toBe($operator->id)
        ->and($workOrder->activation_acceptance_note)->toBe('Customer confirmed service handover.')
        ->and(WorkOrderEvent::query()->where('work_order_id', $workOrder->id)->where('event_type', 'activation_accepted')->exists())->toBeTrue();

    $this->actingAs($operator)
        ->post(route('operations.work-orders.complete', $workOrder->public_id), ['idempotency_key' => '0198d9a4-0e80-72bb-9ef8-44a7bf6c2199'])
        ->assertRedirect(route('operations.work-orders'))
        ->assertSessionHas('success', 'Work order WO-INSTALL-001 completed.');

    app(Tenancy::class)->set($tenant);
    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Completed)
        ->and($service->refresh()->status)->toBe(ServiceStatus::Active);
});
