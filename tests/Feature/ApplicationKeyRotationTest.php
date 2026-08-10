<?php

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
