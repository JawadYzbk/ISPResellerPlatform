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

it('shows manager-level daily collector totals across shifts', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $manager = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'shift-manager@example.test', 'password' => Hash::make('password'), 'role' => 'billing_manager']);
    $collector = User::create(['tenant_id' => $tenant->id, 'name' => 'Field collector', 'email' => 'shift-collector@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $manager->assignRole('billing_manager');
    $collector->assignRole('collector');
    $managerShift = CashShift::create(['user_id' => $manager->id, 'status' => CashShiftStatus::Closed, 'opened_at' => now()->startOfDay(), 'closed_at' => now(), 'system_totals' => ['USD' => 1000], 'declared_totals' => ['USD' => 1000], 'variance' => false]);
    $collectorShift = CashShift::create(['user_id' => $collector->id, 'status' => CashShiftStatus::Closed, 'opened_at' => now()->startOfDay(), 'closed_at' => now(), 'system_totals' => ['USD' => 2000], 'declared_totals' => ['USD' => 2000], 'variance' => false]);
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    Payment::create(['cash_shift_id' => $managerShift->id, 'customer_id' => $customer->id, 'number' => 'RCT-REPORT-001', 'status' => 'posted', 'amount' => 1000, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'shift-report-001', 'received_at' => now(), 'actor_id' => $manager->id]);
    Payment::create(['cash_shift_id' => $collectorShift->id, 'customer_id' => $customer->id, 'number' => 'RCT-REPORT-002', 'status' => 'posted', 'amount' => 2000, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'shift-report-002', 'received_at' => now(), 'actor_id' => $collector->id]);

    $this->actingAs($manager)
        ->get(route('billing.shifts', ['date' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewReport', true)
            ->where('dailyReport.payment_count', 2)
            ->where('dailyReport.totals.USD', 3000)
            ->where('shifts.total', 2)
        );
});
