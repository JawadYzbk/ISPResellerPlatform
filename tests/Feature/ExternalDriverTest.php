<?php

use App\Domain\Network\ExternalDriver;
use App\Enums\ProvisioningMode;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('posts an allowlisted external network command without service credentials', function (): void {
    config([
        'services.external_network.endpoint' => 'https://oss.example.test/network',
        'services.external_network.token' => 'external-token',
    ]);
    Http::fake(['https://oss.example.test/network' => Http::response(['accepted' => true, 'secret' => 'must-not-be-stored'], 202)]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create([
        'provisioning_mode' => ProvisioningMode::External,
        'password_encrypted' => 'must-not-leave-the-app',
    ]);
    $command = NetworkCommand::create([
        'service_id' => $service->id,
        'action' => 'throttle',
        'desired_state_version' => 1,
        'status' => 'pending',
        'payload' => ['fup_profile' => 'fup-1', 'secret' => 'must-be-dropped'],
    ]);

    $result = app(ExternalDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success')
        ->and($result->data)->toMatchArray(['http_status' => 202])
        ->and($result->data)->not->toHaveKey('response');
    Http::assertSent(function ($request) use ($service, $command): bool {
        $data = $request->data();

        return $request->url() === 'https://oss.example.test/network'
            && $request->hasHeader('Authorization', 'Bearer external-token')
            && $data['command_id'] === $command->public_id
            && $data['service']['public_id'] === $service->public_id
            && $data['payload'] === ['fup_profile' => 'fup-1']
            && ! array_key_exists('password', $data['service']);
    });
});

it('fails safely when the external endpoint is not configured', function (): void {
    config(['services.external_network.endpoint' => null]);
    Http::fake();
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['provisioning_mode' => ProvisioningMode::External]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'desired_state_version' => 1, 'status' => 'pending']);

    $result = app(ExternalDriver::class)->execute($service, $command);

    expect($result->status)->toBe('failure')->and($result->message)->toBe('external_endpoint_not_configured');
    Http::assertNothingSent();
});
