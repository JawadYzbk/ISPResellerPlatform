<?php

use App\Enums\WorkOrderStatus;
use App\Models\MediaUpload;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

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

it('links evidence to an assigned work order and keeps it out of another technician response', function (): void {
    Storage::fake('local');
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $technician = User::create(['tenant_id' => $tenant->id, 'name' => 'Assigned Tech', 'email' => 'assigned-media-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    $otherTechnician = User::create(['tenant_id' => $tenant->id, 'name' => 'Other Tech', 'email' => 'other-media-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $technician->assignRole('technician');
    $otherTechnician->assignRole('technician');
    $workOrder = WorkOrder::create([
        'number' => 'WO-MEDIA-001',
        'type' => 'installation',
        'assigned_to' => $technician->id,
        'status' => WorkOrderStatus::Assigned,
    ]);
    $token = $technician->createToken('field-device', ['api', 'staff:technician'])->plainTextToken;

    $upload = $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/v1/technician/work-orders/'.$workOrder->public_id.'/media', [
        'file' => UploadedFile::fake()->image('router-rack.jpg', 80, 80),
        'purpose' => 'evidence',
    ]);
    $upload->assertCreated()
        ->assertJsonPath('purpose', 'evidence')
        ->assertJsonPath('work_order_id', $workOrder->public_id)
        ->assertJsonMissing(['path', 'sha256']);

    $this->withToken($token)->getJson('/api/v1/technician/work-orders/'.$workOrder->public_id)
        ->assertOk()
        ->assertJsonPath('media.0.id', $upload->json('id'))
        ->assertJsonPath('media.0.filename', 'router-rack.jpg');
    $this->flushHeaders();
    Sanctum::actingAs($otherTechnician, ['staff:technician']);
    $otherShow = $this->getJson('/api/v1/technician/work-orders/'.$workOrder->public_id);
    $otherShow->assertNotFound();
    $otherUpload = $this->post('/api/v1/technician/work-orders/'.$workOrder->public_id.'/media', [
        'file' => UploadedFile::fake()->image('unauthorized.jpg', 80, 80),
    ]);
    $otherUpload->assertNotFound();
});
