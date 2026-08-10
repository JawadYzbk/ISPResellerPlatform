<?php

use App\Models\Customer;
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

it('uploads and downloads a tenant-private customer document', function (): void {
    Storage::fake('local');
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Customer care', 'email' => 'customer-documents@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $customer = Customer::factory()->create();

    $this->actingAs($user)
        ->post(route('customers.documents.store', $customer->public_id), ['file' => UploadedFile::fake()->create('contract.pdf', 12, 'application/pdf')])
        ->assertRedirect(route('customers.show', $customer->public_id));

    app(Tenancy::class)->set($tenant);
    $document = MediaUpload::query()->where('customer_id', $customer->id)->firstOrFail();
    Storage::disk('local')->assertExists($document->path);
    $this->actingAs($user)
        ->get(route('customers.show', $customer->public_id))
        ->assertInertia(fn ($page) => $page
            ->where('customer.documents.0.filename', 'contract.pdf')
            ->where('customer.documents.0.download_url', route('operations.media.download', $document->public_id))
        );
    $this->actingAs($user)
        ->get(route('operations.media.download', $document->public_id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('does not expose unlinked media through the operator download route', function (): void {
    Storage::fake('local');
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Customer care', 'email' => 'customer-documents-unlinked@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $path = 'media/'.$tenant->id.'/unlinked.jpg';
    Storage::disk('local')->put($path, 'unlinked');
    $media = MediaUpload::create([
        'uploaded_by_id' => $user->id,
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'unlinked.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 8,
        'sha256' => str_repeat('b', 64),
        'purpose' => 'other',
    ]);

    $this->actingAs($user)->get(route('operations.media.download', $media->public_id))->assertNotFound();
});
