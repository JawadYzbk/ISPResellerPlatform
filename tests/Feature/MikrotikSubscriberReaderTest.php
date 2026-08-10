<?php

use App\Domain\Network\MikrotikSubscriberReader;
use App\Models\Router;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('reads RouterOS PPP secrets through the network boundary', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'api_port' => 8443, 'username' => 'api', 'password_encrypted' => 'router-secret']);
    Http::fake(['https://router.example.test:8443/rest/ppp/secret' => Http::response([['name' => 'ada.home', 'comment' => 'svc:01ARZ3NDEKTSV4RRFFQ69G5FAV', 'password' => 'must-not-leak']], 200)]);

    $subscribers = app(MikrotikSubscriberReader::class)->read($router);

    expect($subscribers)->toHaveCount(1)
        ->and($subscribers[0]['name'])->toBe('ada.home');
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization'));
});

it('enables a RouterOS PPP secret only through the explicit writer operation', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'api_port' => 8443, 'username' => 'api', 'password_encrypted' => 'router-secret']);
    Http::fake(['https://router.example.test:8443/rest/ppp/secret/*' => Http::response([], 200)]);

    app(MikrotikSubscriberReader::class)->enable($router, '*1');

    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && $request->url() === 'https://router.example.test:8443/rest/ppp/secret/%2A1'
        && $request['disabled'] === 'false');
});
