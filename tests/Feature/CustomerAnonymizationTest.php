<?php

use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('anonymizes personal data while preserving the financial customer identity and audit trail', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'anonymize-owner@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner', 'last_authenticated_at' => now()]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $customer = Customer::factory()->create(['email' => 'person@example.test', 'address' => 'Private address']);
    $service = Service::factory()->create(['customer_id' => $customer->id]);
    $publicId = $customer->public_id;

    $this->actingAs($user)->post(route('customers.anonymize', $publicId))->assertRedirect(route('customers.show', $publicId));

    app(Tenancy::class)->set($tenant);
    $anonymized = Customer::query()->findOrFail($customer->id);
    expect($anonymized->public_id)->toBe($publicId)
        ->and($anonymized->first_name)->toBe('Anonymized')
        ->and($anonymized->email)->toBeNull()
        ->and($anonymized->address)->toBeNull()
        ->and($anonymized->phone)->toStartWith('ANON-')
        ->and($anonymized->anonymized_at)->not->toBeNull()
        ->and(Service::query()->whereKey($service->id)->value('customer_id'))->toBe($customer->id)
        ->and(AuditEvent::query()->where('description', 'Customer anonymized')->count())->toBe(1);

    $this->actingAs($user)->post(route('customers.anonymize', $publicId))->assertRedirect();
    app(Tenancy::class)->set($tenant);
    expect(AuditEvent::query()->where('description', 'Customer anonymized')->count())->toBe(1);
});

it('requires the anonymization capability and recent authentication', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'anonymize-auth', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'anonymize-ops@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $customer = Customer::factory()->create();

    $user->forceFill(['last_authenticated_at' => now()])->save();
    $this->actingAs($user)->post(route('customers.anonymize', $customer->public_id))->assertForbidden();

    expect($user->can('customers.anonymize'))->toBeFalse();
});
