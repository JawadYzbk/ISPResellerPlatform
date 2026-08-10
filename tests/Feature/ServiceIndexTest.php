<?php

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders a tenant-scoped service queue', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Staff', 'email' => 'staff@example.test', 'password' => Hash::make('password'), 'role' => 'reseller_staff']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('reseller_staff');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);

    $this->actingAs($user)->get('/services')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Services/Index')->where('services.data.0.id', $service->id));
});
