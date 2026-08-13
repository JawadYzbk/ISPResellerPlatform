<?php

use App\Actions\CreateRenewalInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function serviceAddonOperator(Tenant $tenant): User
{
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Billing Manager',
        'email' => 'billing-'.$tenant->slug.'@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
        'last_authenticated_at' => now(),
    ]);

    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    return $user;
}

function serviceAddonFixture(string $slug = 'northline'): array
{
    $tenant = Tenant::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);
    app(Tenancy::class)->set($tenant);

    $user = serviceAddonOperator($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500, 'duration_days' => 30]);
    $plan->prices()->create([
        'currency' => 'USD',
        'amount_minor' => 3500,
        'effective_from' => now()->subDay(),
    ]);
    $service = Service::factory()->for($customer)->for($plan)->create([
        'status' => ServiceStatus::Active,
        'expires_at' => now()->addDay(),
    ]);

    return compact('tenant', 'user', 'customer', 'plan', 'service');
}

it('attaches, invoices, replays, and cancels a recurring service add-on', function (): void {
    ['tenant' => $tenant, 'user' => $user, 'customer' => $customer, 'service' => $service] = serviceAddonFixture();
    $addon = Addon::create([
        'name' => 'Static IP',
        'slug' => 'static-ip',
        'description' => 'One public address',
        'amount_minor' => 500,
        'currency' => 'USD',
        'billing_period_days' => 30,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->post(route('services.addons.attach', $service->public_id), [
            'addon_id' => $addon->public_id,
            'quantity' => 2,
            'starts_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('services.show', $service->public_id));

    app(Tenancy::class)->set($tenant);
    $serviceAddon = ServiceAddon::query()->sole();
    expect($serviceAddon->quantity)->toBe(2)
        ->and($serviceAddon->status)->toBe('active');

    $first = app(CreateRenewalInvoice::class)->handle($customer, $service, $user);
    $replayed = app(CreateRenewalInvoice::class)->handle($customer, $service, $user);

    expect($first->is($replayed))->toBeTrue()
        ->and($first->status)->toBe(InvoiceStatus::Issued)
        ->and($first->total_amount)->toBe(4500)
        ->and($first->lines()->count())->toBe(2)
        ->and($first->lines()->where('price_snapshot->kind', 'recurring_addon')->sole()->quantity)->toBe(2)
        ->and($first->lines()->where('price_snapshot->kind', 'recurring_addon')->sole()->unit_amount)->toBe(500);

    $this->actingAs($user)
        ->delete(route('services.addons.cancel', [$service->public_id, $serviceAddon->public_id]))
        ->assertRedirect(route('services.show', $service->public_id));

    app(Tenancy::class)->set($tenant);
    expect($serviceAddon->refresh()->status)->toBe('cancelled')
        ->and($serviceAddon->ends_at)->not->toBeNull()
        ->and(Invoice::count())->toBe(1);
});

it('rolls back renewal creation when an add-on currency does not match the plan', function (): void {
    ['customer' => $customer, 'service' => $service, 'user' => $user] = serviceAddonFixture('eastline');
    $usdAddon = Addon::create([
        'name' => 'Static IP',
        'slug' => 'static-ip-usd',
        'amount_minor' => 500,
        'currency' => 'USD',
        'billing_period_days' => 30,
        'status' => 'active',
    ]);
    $eurAddon = Addon::create([
        'name' => 'Managed Wi-Fi',
        'slug' => 'managed-wifi-eur',
        'amount_minor' => 700,
        'currency' => 'EUR',
        'billing_period_days' => 30,
        'status' => 'active',
    ]);
    ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $usdAddon->id,
        'quantity' => 1,
        'starts_at' => now()->toDateString(),
        'status' => 'active',
    ]);
    ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $eurAddon->id,
        'quantity' => 1,
        'starts_at' => now()->toDateString(),
        'status' => 'active',
    ]);

    expect(fn () => app(CreateRenewalInvoice::class)->handle($customer, $service, $user))
        ->toThrow(DomainException::class, 'renewal invoice uses USD');
    expect(Invoice::count())->toBe(0);
});

it('keeps one-off catalog add-ons out of service renewals', function (): void {
    ['service' => $service, 'user' => $user] = serviceAddonFixture('southline');
    $addon = Addon::create([
        'name' => 'Installation fee',
        'slug' => 'installation-fee',
        'amount_minor' => 1000,
        'currency' => 'USD',
        'billing_period_days' => null,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->post(route('services.addons.attach', $service->public_id), [
            'addon_id' => $addon->public_id,
            'quantity' => 1,
            'starts_at' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('addon_id');

    expect(ServiceAddon::count())->toBe(0);
});
