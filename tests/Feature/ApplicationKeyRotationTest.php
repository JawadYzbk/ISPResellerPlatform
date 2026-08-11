<?php

use App\Models\RadiusNas;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('rotates encrypted credentials in one command without changing plaintext', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['password_encrypted' => 'keep-this-secret']);
    $newKey = 'base64:'.base64_encode(random_bytes(32));

    $this->artisan('security:rotate-app-key', ['--new-key' => $newKey])
        ->assertExitCode(0)
        ->expectsOutputToContain('Re-encrypted 1 secret record(s).');

    $ciphertext = (string) DB::table('services')->where('id', $service->id)->value('password_encrypted');
    $bytes = base64_decode(substr($newKey, 7), true);
    expect($bytes)->toBeString();
    $newEncrypter = new Encrypter($bytes, 'AES-256-CBC');
    expect($newEncrypter->decrypt($ciphertext, false))->toBe('keep-this-secret');
});

it('rotates router and FreeRADIUS shared secrets', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create([
        'name' => 'Core',
        'host' => 'radius.example.test',
        'username' => 'api',
        'password_encrypted' => 'router-password',
        'radius_secret_encrypted' => 'router-radius-secret',
    ]);
    $nas = RadiusNas::create([
        'nasname' => $router->host,
        'shortname' => $router->name,
        'secret' => 'nas-radius-secret',
        'coa_port' => 1700,
    ]);
    $newKey = 'base64:'.base64_encode(random_bytes(32));

    $this->artisan('security:rotate-app-key', ['--new-key' => $newKey])
        ->assertExitCode(0)
        ->expectsOutputToContain('Re-encrypted 3 secret record(s).');

    $bytes = base64_decode(substr($newKey, 7), true);
    expect($bytes)->toBeString();
    $newEncrypter = new Encrypter($bytes, 'AES-256-CBC');
    $routerRow = DB::table('routers')->where('id', $router->id)->first();
    $nasRow = DB::table('radius_nas')->where('id', $nas->id)->first();
    expect($newEncrypter->decrypt($routerRow->radius_secret_encrypted, false))->toBe('router-radius-secret')
        ->and($newEncrypter->decrypt($nasRow->secret, false))->toBe('nas-radius-secret');
});
