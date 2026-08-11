<?php

use App\Actions\CreateRouter;
use App\Actions\UpdateRouter;
use App\Domain\Radius\RadiusSyncService;
use App\Domain\Services\ServiceStateMachine;
use App\Enums\ProvisioningMode;
use App\Enums\ServiceStatus;
use App\Models\RadiusGroupReply;
use App\Models\RadiusNas;
use App\Models\RadiusUserGroup;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('keeps the FreeRADIUS integration on stock SQL table names', function (): void {
    foreach (['radcheck', 'radreply', 'radgroupcheck', 'radgroupreply', 'radusergroup', 'radacct', 'radpostauth', 'nas'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('writes deterministic FreeRADIUS rows from a service plan and status', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);

    app(RadiusSyncService::class)->sync($service);

    expect(RadiusUserGroup::firstOrFail()->groupname)->toBe('plan-'.$service->plan_id)
        ->and(RadiusGroupReply::firstOrFail()->value)->toBe($service->plan->upload_kbps.'k/'.$service->plan->download_kbps.'k');

    $service->forceFill(['status' => ServiceStatus::Suspended])->save();
    app(RadiusSyncService::class)->sync($service->refresh());

    expect(RadiusUserGroup::firstOrFail()->groupname)->toBe('suspended');
});

it('synchronizes radius state when a radius service transitions', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['provisioning_mode' => ProvisioningMode::Radius, 'status' => ServiceStatus::Pending]);

    app(ServiceStateMachine::class)->transition($service, ServiceStatus::Active);

    expect(RadiusUserGroup::firstOrFail()->groupname)->toBe('plan-'.$service->plan_id);
});

it('does not publish a NAS without a configured RADIUS shared secret', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);

    app(CreateRouter::class)->handle([
        'name' => 'API-only router',
        'host' => 'api.example.test',
        'api_port' => 8729,
        'username' => 'api',
        'password' => 'router-secret',
        'coa_port' => 1700,
        'tls_verify' => true,
    ], $tenant);

    expect(RadiusNas::query()->count())->toBe(0);
});

it('populates stock FreeRADIUS NAS records from router lifecycle changes', function (): void {
    $tenant = Tenant::create(['name' => 'Westline', 'slug' => 'westline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);

    $router = app(CreateRouter::class)->handle([
        'name' => 'Core RADIUS',
        'host' => 'radius.example.test',
        'api_port' => 8729,
        'username' => 'api',
        'password' => 'router-secret',
        'radius_secret' => 'shared-secret',
        'coa_port' => 1700,
        'tls_verify' => true,
    ], $tenant);

    $nas = RadiusNas::firstOrFail();
    expect($nas->nasname)->toBe('radius.example.test')
        ->and($nas->shortname)->toBe('Core RADIUS')
        ->and($nas->secret)->toBe('shared-secret')
        ->and($nas->toArray())->not->toHaveKey('secret')
        ->and(RadiusNas::query()->count())->toBe(1);

    app(UpdateRouter::class)->handle($router, [
        'name' => 'Core RADIUS Updated',
        'host' => 'radius-new.example.test',
        'api_port' => 8729,
        'username' => 'api',
        'password' => '',
        'radius_secret' => 'new-shared-secret',
        'coa_port' => 1812,
        'tls_verify' => true,
    ]);

    expect(RadiusNas::query()->count())->toBe(1)
        ->and(RadiusNas::firstOrFail()->nasname)->toBe('radius-new.example.test')
        ->and(RadiusNas::firstOrFail()->secret)->toBe('new-shared-secret')
        ->and(RadiusNas::firstOrFail()->coa_port)->toBe(1812)
        ->and(RadiusNas::query()->where('nasname', 'radius.example.test')->exists())->toBeFalse();
});
