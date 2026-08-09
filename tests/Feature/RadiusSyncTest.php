<?php

use App\Domain\Radius\RadiusSyncService;
use App\Enums\ServiceStatus;
use App\Models\RadiusGroupReply;
use App\Models\RadiusUserGroup;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
