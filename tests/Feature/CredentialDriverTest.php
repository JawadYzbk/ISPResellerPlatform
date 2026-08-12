<?php

use App\Domain\Network\CredentialDriver;
use App\Enums\CredentialStatus;
use App\Enums\ProvisioningMode;
use App\Models\CredentialAssignment;
use App\Models\CredentialBatch;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\UpstreamCredential;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('activates one available credential and releases it on suspension', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create(['name' => 'Upstream', 'code' => 'UP-01']);
    $batch = CredentialBatch::create(['supplier_id' => $supplier->id, 'reference' => 'BATCH-01', 'imported_at' => now()]);
    $credential = UpstreamCredential::create([
        'credential_batch_id' => $batch->id,
        'identifier' => 'cust-001',
        'secret' => 'secret',
        'lookup_hash' => hash('sha256', 'cust-001'),
        'status' => CredentialStatus::Available,
    ]);
    $service = Service::factory()->create(['provisioning_mode' => ProvisioningMode::UpstreamCredential]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'desired_state_version' => 1, 'status' => 'pending']);

    $result = app(CredentialDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success')
        ->and($credential->refresh()->status)->toBe(CredentialStatus::Active)
        ->and($credential->assigned_service_id)->toBe($service->id)
        ->and(CredentialAssignment::query()->where('service_id', $service->id)->whereNull('released_at')->count())->toBe(1);

    $suspend = NetworkCommand::create(['service_id' => $service->id, 'action' => 'suspend', 'desired_state_version' => 2, 'status' => 'pending']);
    $release = app(CredentialDriver::class)->execute($service, $suspend);

    expect($release->status)->toBe('success')
        ->and($credential->refresh()->status)->toBe(CredentialStatus::Available)
        ->and($credential->assigned_service_id)->toBeNull()
        ->and(CredentialAssignment::query()->where('service_id', $service->id)->whereNotNull('released_at')->count())->toBe(1);
});

it('fails activation when the inventory has no usable credential', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['provisioning_mode' => ProvisioningMode::UpstreamCredential]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'desired_state_version' => 1, 'status' => 'pending']);

    $result = app(CredentialDriver::class)->execute($service, $command);

    expect($result->status)->toBe('failure')->and($result->message)->toBe('no_available_upstream_credential');
});

it('keeps an upstream credential assigned for an operator disconnect', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create(['name' => 'Upstream', 'code' => 'UP-01']);
    $batch = CredentialBatch::create(['supplier_id' => $supplier->id, 'reference' => 'BATCH-01', 'imported_at' => now()]);
    $credential = UpstreamCredential::create([
        'credential_batch_id' => $batch->id,
        'identifier' => 'cust-001',
        'secret' => 'secret',
        'lookup_hash' => hash('sha256', 'cust-001'),
        'status' => CredentialStatus::Active,
    ]);
    $service = Service::factory()->create(['provisioning_mode' => ProvisioningMode::UpstreamCredential]);
    $credential->forceFill(['assigned_service_id' => $service->id])->save();
    CredentialAssignment::create(['upstream_credential_id' => $credential->id, 'service_id' => $service->id, 'assigned_at' => now()]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'disconnect', 'desired_state_version' => 1, 'status' => 'pending', 'payload' => ['reason' => 'operator_disconnect']]);

    $result = app(CredentialDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success')
        ->and($result->data['released'])->toBeFalse()
        ->and($credential->refresh()->status)->toBe(CredentialStatus::Active)
        ->and(CredentialAssignment::query()->where('service_id', $service->id)->whereNull('released_at')->exists())->toBeTrue();
});
