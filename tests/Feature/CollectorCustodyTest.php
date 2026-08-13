<?php

use App\Actions\CreateCollectorCustodyEntry;
use App\Actions\GetCollectorCustodyPosition;
use App\Actions\ReviewCollectorCustodyEntry;
use App\Enums\CashShiftStatus;
use App\Enums\PaymentStatus;
use App\Models\CashShift;
use App\Models\CollectorCustodyEntry;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/** @return array{Tenant, User, User} */
function collectorCustodyWorkspace(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Custody ISP', 'slug' => 'custody-isp']);
    app(Tenancy::class)->set($tenant);
    $manager = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Custody Manager',
        'email' => 'custody-manager@example.test',
        'password' => Hash::make('password'),
        'role' => 'operations_manager',
    ]);
    $collector = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Custody Collector',
        'email' => 'custody-collector@example.test',
        'password' => Hash::make('password'),
        'role' => 'collector',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $manager->assignRole('operations_manager');
    $collector->assignRole('collector');

    return [$tenant, $manager, $collector];
}

it('keeps cash, float, advances and approved debits in an auditable custody position', function (): void {
    [, $manager, $collector] = collectorCustodyWorkspace();
    $customer = Customer::factory()->create();
    CashShift::create([
        'user_id' => $collector->id,
        'status' => CashShiftStatus::Closed,
        'opened_at' => now()->subDay(),
        'closed_at' => now(),
        'opening_float' => ['USD' => 100],
    ]);
    Payment::create([
        'number' => 'RCT-CUSTODY-001',
        'customer_id' => $customer->id,
        'actor_id' => $collector->id,
        'status' => PaymentStatus::Posted,
        'method' => 'cash',
        'amount' => 2000,
        'currency' => 'USD',
        'idempotency_key' => 'custody-cash-payment',
        'received_at' => now(),
    ]);
    Payment::create([
        'number' => 'RCT-CUSTODY-002',
        'customer_id' => $customer->id,
        'actor_id' => $collector->id,
        'status' => PaymentStatus::Posted,
        'method' => 'whish',
        'amount' => 9000,
        'currency' => 'USD',
        'idempotency_key' => 'custody-whish-payment',
        'received_at' => now(),
    ]);
    app(CreateCollectorCustodyEntry::class)->handle($manager, $collector, null, [
        'type' => 'advance',
        'amount' => 500,
        'currency' => 'USD',
        'description' => 'Morning float top-up',
    ]);
    $expense = app(CreateCollectorCustodyEntry::class)->handle($collector, $collector, null, [
        'type' => 'expense',
        'amount' => 200,
        'currency' => 'USD',
        'description' => 'Fuel',
    ]);

    $before = app(GetCollectorCustodyPosition::class)->handle($collector);
    expect($expense->status)->toBe('pending')
        ->and($before['balances']['USD'])->toBe(2600)
        ->and($before['pending_count'])->toBe(1);

    app(ReviewCollectorCustodyEntry::class)->handle($manager, $expense, 'posted', 'Receipt checked');
    $after = app(GetCollectorCustodyPosition::class)->handle($collector);
    expect($after['balances']['USD'])->toBe(2400)
        ->and($after['cash_payment_count'])->toBe(1)
        ->and($after['pending_count'])->toBe(0)
        ->and($expense->refresh()->review_note)->toBe('Receipt checked');
});

it('limits collector submissions and prevents a second review', function (): void {
    [, $manager, $collector] = collectorCustodyWorkspace();

    expect(fn () => app(CreateCollectorCustodyEntry::class)->handle($collector, $collector, null, [
        'type' => 'advance',
        'amount' => 100,
        'currency' => 'USD',
        'description' => 'Self-issued advance',
    ]))->toThrow(DomainException::class, 'Only a manager can record advances or adjustments.');

    $handover = app(CreateCollectorCustodyEntry::class)->handle($collector, $collector, null, [
        'type' => 'handover',
        'amount' => 100,
        'currency' => 'USD',
        'description' => 'End-of-day handover',
    ]);
    app(ReviewCollectorCustodyEntry::class)->handle($manager, $handover, 'rejected', 'Count did not match');

    expect(fn () => app(ReviewCollectorCustodyEntry::class)->handle($manager, $handover, 'posted'))
        ->toThrow(DomainException::class, 'already been reviewed')
        ->and(CollectorCustodyEntry::query()->where('status', 'rejected')->count())->toBe(1);
});
