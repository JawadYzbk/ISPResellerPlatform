<?php

use App\Domain\Network\CredentialDriver;
use App\Domain\Network\DriverManager;
use App\Domain\Network\ExternalDriver;
use App\Domain\Network\FakeDriver;
use App\Domain\Network\ManualDriver;
use App\Domain\Network\MikrotikApiDriver;
use App\Domain\Network\RadiusDriver;
use App\Domain\Radius\CoaClient;
use App\Domain\Radius\RadiusSyncService;
use App\Domain\Radius\UdpRadiusTransport;
use App\Enums\ProvisioningMode;
use App\Models\Service;

beforeEach(function (): void {
    $this->manual = new ManualDriver;
    $this->mikrotik = new MikrotikApiDriver;
    $this->radius = new RadiusDriver(new RadiusSyncService, new CoaClient(new UdpRadiusTransport));
    $this->external = new ExternalDriver;
    $this->credential = new CredentialDriver;
    $this->manager = new DriverManager($this->manual, $this->mikrotik, $this->radius, $this->external, $this->credential);
});

it('routes manual and MikroTik provisioning modes to their drivers', function (): void {
    $manual = new Service(['provisioning_mode' => ProvisioningMode::Manual]);
    $mikrotik = new Service(['provisioning_mode' => ProvisioningMode::Mikrotik]);

    expect($this->manager->for($manual))->toBe($this->manual)
        ->and($this->manager->for($mikrotik))->toBe($this->mikrotik);
});

it('allows all network tests to use the programmable fake driver', function (): void {
    $fake = new FakeDriver;
    $manager = new DriverManager($this->manual, $this->mikrotik, $this->radius, $this->external, $this->credential, $fake);
    $service = new Service(['provisioning_mode' => ProvisioningMode::Mikrotik]);

    expect($manager->for($service))->toBe($fake);
});

it('resolves external services through the configured external driver', function (): void {
    $service = new Service(['provisioning_mode' => ProvisioningMode::External]);

    expect($this->manager->for($service))->toBe($this->external);
});

it('resolves upstream credential services through the credential driver when configured', function (): void {
    $service = new Service(['provisioning_mode' => ProvisioningMode::UpstreamCredential]);

    expect($this->manager->for($service))->toBe($this->credential);
});
