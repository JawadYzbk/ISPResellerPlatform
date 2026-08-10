<?php

use App\Models\MediaUpload;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores a tenant-scoped technician image and returns a public media id', function (): void {
    Storage::fake('local');
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Technician', 'email' => 'media-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('technician');
    $token = $user->createToken('field-device', ['api', 'staff:technician'])->plainTextToken;

    $response = $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/v1/technician/uploads', ['file' => UploadedFile::fake()->image('site.jpg', 80, 80)]);

    $response->assertCreated()->assertJsonStructure(['id', 'filename', 'mime_type', 'size_bytes'])->assertJsonMissing(['path', 'sha256']);
    app(Tenancy::class)->set($tenant);
    $media = MediaUpload::query()->firstOrFail();
    Storage::disk('local')->assertExists($media->path);
    expect($response->json('id'))->toBe($media->public_id)
        ->and($media->tenant_id)->toBe($tenant->id);
});

it('rejects non-image technician uploads', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Technician', 'email' => 'media-tech-text@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('technician');
    $token = $user->createToken('field-device', ['api', 'staff:technician'])->plainTextToken;

    $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/v1/technician/uploads', ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])->assertUnprocessable();
});
