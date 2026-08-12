<?php

use App\Domain\Network\CredentialDriver;
use App\Domain\Network\DriverManager;
use App\Domain\Network\ExternalDriver;
use App\Domain\Network\FakeDriver;
use App\Domain\Network\ManualDriver;
use App\Domain\Network\NullDriver;
use App\Enums\ProvisioningMode;
use App\Models\Service;

it('resolves manual and deferred modes without touching a router', function (): void {
    $manager = new DriverManager(new ManualDriver, new NullDriver);
    $manual = new Service(['provisioning_mode' => ProvisioningMode::Manual]);
    $mikrotik = new Service(['provisioning_mode' => ProvisioningMode::Mikrotik]);

    expect($manager->for($manual))->toBeInstanceOf(ManualDriver::class)
        ->and($manager->for($mikrotik))->toBeInstanceOf(NullDriver::class);
});

it('allows all network tests to use the programmable fake driver', function (): void {
    $fake = new FakeDriver;
    $manager = new DriverManager(new ManualDriver, new NullDriver, $fake);
    $service = new Service(['provisioning_mode' => ProvisioningMode::Mikrotik]);

    expect($manager->for($service))->toBe($fake);
});

it('resolves external services through the configured external driver', function (): void {
    $external = new ExternalDriver;
    $manager = new DriverManager(new ManualDriver, new NullDriver, null, null, null, $external);
    $service = new Service(['provisioning_mode' => ProvisioningMode::External]);

    expect($manager->for($service))->toBe($external);
});

it('resolves upstream credential services through the credential driver when configured', function (): void {
    $credential = new CredentialDriver;
    $manager = new DriverManager(new ManualDriver, new NullDriver, null, null, null, null, $credential);
    $service = new Service(['provisioning_mode' => ProvisioningMode::UpstreamCredential]);

    expect($manager->for($service))->toBe($credential);
});
