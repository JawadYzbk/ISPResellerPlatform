<?php

use App\Actions\TestRouterConnection;
use App\Models\Router;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('tests a router connection without exposing encrypted credentials', function (): void {
    Http::fake(['https://router.example.test/rest/system/resource' => Http::response(['version' => '7.15.2', 'board-name' => 'CHR'])]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret', 'tls_verify' => true]);

    $result = app(TestRouterConnection::class)->handle($router);

    expect($result)->toMatchArray(['status' => 'online', 'version' => '7.15.2', 'identity' => 'CHR'])
        ->and($router->toArray())->not->toHaveKey('password_encrypted')
        ->and($router->refresh()->status)->toBe('online');
});

it('categorizes rejected router credentials', function (): void {
    Http::fake(['https://router.example.test/rest/system/resource' => Http::response([], 401)]);
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);

    expect(fn (): array => app(TestRouterConnection::class)->handle($router))->toThrow(DomainException::class, 'router_credentials_rejected');
});
