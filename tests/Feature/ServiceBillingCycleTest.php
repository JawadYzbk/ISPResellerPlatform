<?php

use App\Actions\CancelServiceBillingCycleChange;
use App\Actions\CreateRenewalInvoice;
use App\Actions\PreviewServiceBillingCycle;
use App\Actions\RenewService;
use App\Actions\ScheduleServiceBillingCycle;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00', 'Asia/Beirut'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/** @return array{Tenant, User, Service} */
function billingCycleWorkspace(array $serviceAttributes = []): array
{
    $tenant = Tenant::factory()->create(['base_currency' => 'LBP', 'collection_currency' => 'LBP', 'timezone' => 'Asia/Beirut']);
    app(Tenancy::class)->set($tenant);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create([
        'status' => ServiceStatus::Pending,
        'expires_at' => null,
        ...$serviceAttributes,
    ]);
    $service->customer->forceFill(['balance_currency' => 'LBP'])->save();
    $service->plan->prices()->create([
        'currency' => 'LBP',
        'amount_minor' => 31_000,
        'effective_from' => now()->subDay(),
    ]);

    return [$tenant, $user, $service->refresh()];
}

it('sets a first anchor immediately and invoices only the first partial period', function (): void {
    [, $user, $service] = billingCycleWorkspace();

    $updated = app(ScheduleServiceBillingCycle::class)->handle($service, 1, $user);
    $invoice = app(CreateRenewalInvoice::class)->handle($updated->customer, $updated, $user);
    $renewed = app(RenewService::class)->handle($updated, $user);

    expect($updated->billing_anchor_day)->toBe(1)
        ->and($updated->metadata['pending_billing_cycle'] ?? null)->toBeNull()
        ->and($invoice->total_amount)->toBe(19_000)
        ->and($invoice->metadata['billing_cycle_quote']['billable_days'])->toBe(19)
        ->and($invoice->lines->first()->unit_amount)->toBe(19_000)
        ->and(CarbonImmutable::instance($renewed->expires_at)->setTimezone('Asia/Beirut')->toDateTimeString())->toBe('2026-09-01 23:59:59')
        ->and(ServiceEvent::where('event_type', 'billing_cycle_set')->count())->toBe(1);
});

it('previews and applies a prorated anchor transition on the paid renewal', function (): void {
    [, $user, $service] = billingCycleWorkspace([
        'status' => ServiceStatus::Active,
        'billing_anchor_day' => 1,
        'expires_at' => CarbonImmutable::parse('2026-09-01 20:59:59', 'UTC'),
    ]);
    $quote = app(PreviewServiceBillingCycle::class)->handle($service, 15);
    $scheduled = app(ScheduleServiceBillingCycle::class)->handle($service, 15, $user);
    $invoice = app(CreateRenewalInvoice::class)->handle($scheduled->customer, $scheduled, $user);
    $renewed = app(RenewService::class)->handle($scheduled, $user);

    expect($quote->billableDays)->toBe(14)
        ->and($quote->proratedAmount)->toBe(14_000)
        ->and($scheduled->billing_anchor_day)->toBe(1)
        ->and($scheduled->metadata['pending_billing_cycle']['anchor_day'])->toBe(15)
        ->and($invoice->total_amount)->toBe(14_000)
        ->and($renewed->billing_anchor_day)->toBe(15)
        ->and($renewed->metadata['pending_billing_cycle'] ?? null)->toBeNull()
        ->and(CarbonImmutable::instance($renewed->expires_at)->setTimezone('Asia/Beirut')->toDateTimeString())->toBe('2026-09-15 23:59:59');
});

it('cancels a scheduled anchor transition without changing the current cycle', function (): void {
    [, $user, $service] = billingCycleWorkspace([
        'status' => ServiceStatus::Active,
        'billing_anchor_day' => 1,
        'expires_at' => CarbonImmutable::parse('2026-09-01 20:59:59', 'UTC'),
    ]);
    $scheduled = app(ScheduleServiceBillingCycle::class)->handle($service, 15, $user);
    $cancelled = app(CancelServiceBillingCycleChange::class)->handle($scheduled, $user);

    expect($cancelled->billing_anchor_day)->toBe(1)
        ->and($cancelled->metadata['pending_billing_cycle'] ?? null)->toBeNull()
        ->and(ServiceEvent::where('event_type', 'billing_cycle_change_cancelled')->count())->toBe(1);
});

it('locks the quoted cycle while its renewal invoice is open', function (): void {
    [, $user, $service] = billingCycleWorkspace([
        'status' => ServiceStatus::Active,
        'billing_anchor_day' => 1,
        'expires_at' => CarbonImmutable::parse('2026-09-01 20:59:59', 'UTC'),
    ]);
    $scheduled = app(ScheduleServiceBillingCycle::class)->handle($service, 15, $user);
    app(CreateRenewalInvoice::class)->handle($scheduled->customer, $scheduled, $user);

    expect(fn () => app(CancelServiceBillingCycleChange::class)->handle($scheduled->refresh(), $user))
        ->toThrow(DomainException::class, 'Settle or void the open renewal invoice before cancelling this billing-cycle change.')
        ->and(fn () => app(ScheduleServiceBillingCycle::class)->handle($scheduled->refresh(), 20, $user))
        ->toThrow(DomainException::class, 'Settle or void the open renewal invoice before changing this billing cycle.');
});
