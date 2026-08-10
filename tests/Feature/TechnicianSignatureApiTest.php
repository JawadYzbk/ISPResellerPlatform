<?php

use App\Enums\WorkOrderStatus;
use App\Models\MediaUpload;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSignature;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('captures one assigned work-order signature as private media', function (): void {
    Storage::fake('local');
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Technician', 'email' => 'signature-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('technician');
    $order = WorkOrder::create(['number' => 'WO-SIGNATURE-001', 'type' => 'installation', 'assigned_to' => $user->id, 'status' => WorkOrderStatus::InProgress]);
    $token = $user->createToken('field-device', ['api', 'staff:technician'])->plainTextToken;

    $response = $this->withToken($token)->post('/api/v1/technician/work-orders/'.$order->public_id.'/signature', [
        'file' => UploadedFile::fake()->image('signature.png', 180, 80),
        'signer_name' => 'Customer signer',
    ]);

    $response->assertCreated()
        ->assertJsonPath('signer_name', 'Customer signer')
        ->assertJsonPath('media.filename', 'signature.png');
    app(Tenancy::class)->set($tenant);
    $signature = WorkOrderSignature::query()->firstOrFail();
    $media = MediaUpload::query()->findOrFail($signature->media_upload_id);
    Storage::disk('local')->assertExists($media->path);
    $this->withToken($token)->getJson('/api/v1/technician/work-orders/'.$order->public_id)
        ->assertOk()
        ->assertJsonPath('signature.signer_name', 'Customer signer')
        ->assertJsonPath('signature.media_id', $media->public_id);

    $this->withToken($token)->post('/api/v1/technician/work-orders/'.$order->public_id.'/signature', [
        'file' => UploadedFile::fake()->image('second-signature.png', 180, 80),
        'signer_name' => 'Another signer',
    ])->assertStatus(409);
    app(Tenancy::class)->set($tenant);
    expect(WorkOrderSignature::query()->count())->toBe(1)
        ->and(MediaUpload::query()->where('purpose', 'signature')->count())->toBe(1);
});
