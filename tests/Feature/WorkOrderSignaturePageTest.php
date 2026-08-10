<?php

use App\Enums\WorkOrderStatus;
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

it('captures and displays a customer signature on the operator work-order page', function (): void {
    Storage::fake('local');
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'signature-operator@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $order = WorkOrder::create(['number' => 'WO-SIGNATURE-PAGE-001', 'type' => 'installation', 'assigned_to' => $user->id, 'status' => WorkOrderStatus::InProgress]);

    $this->actingAs($user)
        ->post(route('operations.work-orders.signature.store', $order->public_id), [
            'file' => UploadedFile::fake()->image('signature.png', 180, 80),
            'signer_name' => 'Customer signer',
        ])
        ->assertRedirect(route('operations.work-orders.show', $order->public_id));

    app(Tenancy::class)->set($tenant);
    $signature = WorkOrderSignature::query()->firstOrFail();
    $signature->load('media');
    $this->actingAs($user)
        ->get(route('operations.work-orders.show', $order->public_id))
        ->assertInertia(fn ($page) => $page
            ->where('workOrder.signature.signer_name', 'Customer signer')
            ->where('workOrder.signature.download_url', route('operations.media.download', $signature->media->public_id))
        );
});
