<?php

use App\Domain\Network\CredentialDriver;
use App\Domain\Network\DriverManager;
use App\Domain\Network\ExternalDriver;
use App\Domain\Network\FakeDriver;
use App\Domain\Network\ManualDriver;
use App\Domain\Network\MikrotikApiDriver;
use App\Domain\Network\RadiusDriver;
use App\Enums\ProvisioningMode;
use App\Models\Service;

it('resolves real network drivers from the application container', function (ProvisioningMode $mode, string $driver): void {
    expect(app(DriverManager::class)->for(new Service(['provisioning_mode' => $mode])))->toBeInstanceOf($driver);
})->with([
    'manual' => [ProvisioningMode::Manual, ManualDriver::class],
    'MikroTik' => [ProvisioningMode::Mikrotik, MikrotikApiDriver::class],
    'RADIUS' => [ProvisioningMode::Radius, RadiusDriver::class],
    'external' => [ProvisioningMode::External, ExternalDriver::class],
    'upstream credential' => [ProvisioningMode::UpstreamCredential, CredentialDriver::class],
]);

it('uses the fake driver only when it is explicitly registered', function (): void {
    $fake = new FakeDriver;
    app()->instance(FakeDriver::class, $fake);

    expect(app(DriverManager::class)->for(new Service(['provisioning_mode' => ProvisioningMode::Mikrotik])))->toBe($fake);
});
