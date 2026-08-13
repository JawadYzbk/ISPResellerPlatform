<?php

use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\OpticalDevice;
use App\Models\OpticalReading;
use App\Models\Pop;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function opticalOperator(Tenant $tenant): User
{
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Optical operator',
        'email' => 'optical-'.$tenant->id.'@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    return $user;
}

it('registers optical devices and records readings for a tenant', function (): void {
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);
    app(Tenancy::class)->set($tenant);
    $user = opticalOperator($tenant);
    $pop = Pop::create(['name' => 'Central POP', 'code' => 'POP-CENTRAL', 'status' => 'active']);
    $customer = Customer::factory()->create();
    $service = Service::factory()->for($customer)->create(['status' => ServiceStatus::Active]);

    $this->actingAs($user)
        ->get(route('operations.optical'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/Optical')
            ->where('devices', [])
            ->where('canManage', true));

    $this->actingAs($user)
        ->post(route('operations.optical.devices.store'), [
            'pop_id' => $pop->id,
            'name' => 'Central OLT',
            'code' => 'OLT-CENTRAL-01',
            'device_type' => 'olt',
            'vendor' => 'Huawei',
            'model' => 'MA5800',
            'host' => '10.0.0.10',
            'management_port' => 161,
            'status' => 'active',
            'notes' => 'Rack 3, cabinet A',
        ])
        ->assertSessionHasNoErrors();

    app(Tenancy::class)->set($tenant);
    $device = OpticalDevice::query()->where('code', 'OLT-CENTRAL-01')->firstOrFail();

    $this->actingAs($user)
        ->post(route('operations.optical.readings.store'), [
            'optical_device_id' => $device->public_id,
            'service_id' => $service->public_id,
            'onu_serial' => 'HWTC12345678',
            'rx_dbm' => '-18.50',
            'tx_dbm' => '2.20',
            'temperature_c' => '42.00',
            'recorded_at' => '2026-08-13 10:00:00',
        ])
        ->assertSessionHasNoErrors();

    app(Tenancy::class)->set($tenant);
    $reading = OpticalReading::query()->where('optical_device_id', $device->id)->firstOrFail();
    expect($reading->tenant_id)->toBe($tenant->id)
        ->and($reading->service_id)->toBe($service->id)
        ->and($reading->onu_serial)->toBe('HWTC12345678')
        ->and($reading->rx_dbm)->toBe('-18.50');
});

it('rejects optical devices and readings from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = opticalOperator($tenant);

    app(Tenancy::class)->set($otherTenant);
    $otherDevice = OpticalDevice::create([
        'name' => 'Other OLT',
        'code' => 'OTHER-OLT',
        'device_type' => 'olt',
        'status' => 'active',
    ]);

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('operations.optical.readings.store'), [
            'optical_device_id' => $otherDevice->public_id,
            'onu_serial' => 'CROSS-TENANT',
            'rx_dbm' => '-20',
        ])
        ->assertSessionHasErrors('optical_device_id');

    expect(OpticalReading::query()->count())->toBe(0);
});
