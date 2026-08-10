<?php

use App\Actions\OpenCashShift;
use App\Enums\CashShiftStatus;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('opens, displays, and closes the current cashier shift with normalized totals', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'shift-page@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('cashier');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('billing.shifts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Billing/Shifts')->where('currentShift', null)->where('currencies.0', 'USD'));

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)->post(route('billing.shifts.open'))->assertRedirect(route('billing.shifts'));
    app(Tenancy::class)->set($tenant);
    $shift = CashShift::query()->where('user_id', $user->id)->firstOrFail();
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    Payment::create(['cash_shift_id' => $shift->id, 'customer_id' => $customer->id, 'number' => 'RCT-SHIFT-001', 'status' => 'posted', 'amount' => 1200, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'shift-page-001', 'received_at' => now()]);

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->get(route('billing.shifts'))
        ->assertInertia(fn ($page) => $page
            ->where('currentShift.public_id', $shift->public_id)
            ->where('currentShift.system_totals.USD', 1200)
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('billing.shifts.close', $shift->public_id), ['declared_totals' => ['USD' => 1200]])
        ->assertRedirect(route('billing.shifts'));

    app(Tenancy::class)->set($tenant);
    expect($shift->refresh()->status)->toBe(CashShiftStatus::Closed)
        ->and($shift->variance)->toBeFalse();
});

it('requires a variance note when the declared shift total differs', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'shift-variance@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('cashier');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $shift = app(OpenCashShift::class)->handle($user);

    $this->actingAs($user)
        ->post(route('billing.shifts.close', $shift->public_id), ['declared_totals' => ['USD' => 100]])
        ->assertRedirect();

    app(Tenancy::class)->set($tenant);
    expect($shift->refresh()->status)->toBe(CashShiftStatus::Open);
});
