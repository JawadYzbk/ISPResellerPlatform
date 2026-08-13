<?php

use App\Actions\CreateCollectorCustodyEntry;
use App\Actions\CreateOperationalExpense;
use App\Actions\GetCollectorCustodyPosition;
use App\Actions\ReviewOperationalExpense;
use App\Models\ExpenseCategory;
use App\Models\JournalLine;
use App\Models\OperationalExpense;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/** @return array{Tenant, User, User, ExpenseCategory} */
function operationalExpenseWorkspace(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Expense ISP', 'slug' => 'expense-isp']);
    app(Tenancy::class)->set($tenant);
    $manager = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Expense Manager',
        'email' => 'expense-manager@example.test',
        'password' => Hash::make('password'),
        'role' => 'operations_manager',
    ]);
    $collector = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Expense Collector',
        'email' => 'expense-collector@example.test',
        'password' => Hash::make('password'),
        'role' => 'collector',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);

    return [$tenant, $manager->refresh(), $collector->refresh(), ExpenseCategory::query()->firstOrFail()];
}

it('keeps an expense off the ledger until approval and then posts balanced lines', function (): void {
    [, $manager, , $category] = operationalExpenseWorkspace();
    $expense = app(CreateOperationalExpense::class)->handle($manager, [
        'expense_category_id' => $category->id,
        'payment_source' => 'bank',
        'amount' => 12500,
        'currency' => 'USD',
        'description' => 'Office internet backup line',
        'reference' => 'ISP-001',
    ]);

    expect($expense->status)->toBe('pending')
        ->and(JournalLine::query()->count())->toBe(0);

    $posted = app(ReviewOperationalExpense::class)->handle($manager, $expense, 'posted', 'Invoice checked');

    expect($posted->status)->toBe('posted')
        ->and($posted->journal_entry_id)->not->toBeNull()
        ->and($posted->review_note)->toBe('Invoice checked')
        ->and(JournalLine::query()->where('debit_amount', 12500)->count())->toBe(1)
        ->and(JournalLine::query()->where('credit_amount', 12500)->count())->toBe(1);
});

it('posts collector expenses to custody only after approval', function (): void {
    [, $manager, $collector, $category] = operationalExpenseWorkspace();
    app(CreateCollectorCustodyEntry::class)->handle($manager, $collector, null, [
        'type' => 'advance',
        'amount' => 500000,
        'currency' => 'LBP',
        'description' => 'Field float',
    ]);
    $expense = app(CreateOperationalExpense::class)->handle($collector, [
        'expense_category_id' => $category->id,
        'payment_source' => 'collector',
        'amount' => 150000,
        'currency' => 'LBP',
        'description' => 'Generator diesel',
    ]);

    expect(app(GetCollectorCustodyPosition::class)->handle($collector)['balances']['LBP'])->toBe(500000);

    $posted = app(ReviewOperationalExpense::class)->handle($manager, $expense, 'posted');

    expect($posted->collector_id)->toBe($collector->id)
        ->and($posted->collector_custody_entry_id)->not->toBeNull()
        ->and(app(GetCollectorCustodyPosition::class)->handle($collector)['balances']['LBP'])->toBe(350000);
});

it('rejects expenses without changing the ledger and prevents duplicate review', function (): void {
    [, $manager, , $category] = operationalExpenseWorkspace();
    $expense = app(CreateOperationalExpense::class)->handle($manager, [
        'expense_category_id' => $category->id,
        'payment_source' => 'cash',
        'amount' => 2500,
        'currency' => 'USD',
        'description' => 'Unverified purchase',
    ]);

    app(ReviewOperationalExpense::class)->handle($manager, $expense, 'rejected', 'Receipt is missing');

    expect($expense->refresh()->status)->toBe('rejected')
        ->and(JournalLine::query()->count())->toBe(0)
        ->and(fn () => app(ReviewOperationalExpense::class)->handle($manager, $expense, 'posted'))
        ->toThrow(DomainException::class, 'already been reviewed');
});

it('does not approve a collector expense above available custody', function (): void {
    [, $manager, $collector, $category] = operationalExpenseWorkspace();
    $expense = app(CreateOperationalExpense::class)->handle($collector, [
        'expense_category_id' => $category->id,
        'payment_source' => 'collector',
        'amount' => 100,
        'currency' => 'USD',
        'description' => 'Fuel',
    ]);

    expect(fn () => app(ReviewOperationalExpense::class)->handle($manager, $expense, 'posted'))
        ->toThrow(DomainException::class, 'exceeds this collector\'s available cash custody')
        ->and(OperationalExpense::query()->findOrFail($expense->id)->status)->toBe('pending')
        ->and(JournalLine::query()->count())->toBe(0);
});
