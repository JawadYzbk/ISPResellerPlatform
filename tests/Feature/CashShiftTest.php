<?php

use App\Actions\CloseCashShift;
use App\Actions\OpenCashShift;
use App\Enums\CashShiftStatus;
use App\Models\CashShift;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('requires a note for a cash variance and blocks a second open shift', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    $shift = app(OpenCashShift::class)->handle($user);

    expect(fn (): CashShift => app(OpenCashShift::class)->handle($user))->toThrow(DomainException::class)
        ->and(fn (): CashShift => app(CloseCashShift::class)->handle($shift, ['USD' => 100]))->toThrow(DomainException::class);

    $closed = app(CloseCashShift::class)->handle($shift, ['USD' => 100], 'Till short by 100');

    expect($closed->status)->toBe(CashShiftStatus::Closed)->and($closed->variance)->toBeTrue();
});
