<?php

use App\Actions\CancelServicePlanChange;
use App\Actions\ChangeServicePlan;
use App\Actions\RenewService;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\NetworkCommand;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('changes an active service plan immediately with prorated ledger entries and network sync', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $oldPlan = Plan::factory()->create(['amount_minor' => 100, 'currency' => 'USD']);
    $newPlan = Plan::factory()->create(['amount_minor' => 200, 'currency' => 'USD']);
    $oldPlan->prices()->create(['currency' => 'USD', 'amount_minor' => 100, 'effective_from' => now()->subDay()]);
    $newPlan->prices()->create(['currency' => 'USD', 'amount_minor' => 200, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->for($customer)->for($oldPlan)->create([
        'status' => ServiceStatus::Active,
        'activated_at' => now()->subDays(10),
        'expires_at' => now()->addDays(20),
    ]);

    $updated = app(ChangeServicePlan::class)->handle($service, $newPlan, 'immediate');

    expect($updated->plan_id)->toBe($newPlan->id)
        ->and($updated->refresh()->network_state->value)->toBe('pending_sync')
        ->and($customer->refresh()->balance_amount)->toBe(66)
        ->and(JournalEntry::count())->toBe(2)
        ->and(NetworkCommand::query()->where('action', 'change_plan')->count())->toBe(1)
        ->and(ServiceEvent::query()->where('event_type', 'plan_changed')->count())->toBe(1);
});

it('applies a next-cycle plan change during renewal and queues the new network profile', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $oldPlan = Plan::factory()->create(['amount_minor' => 100, 'duration_days' => 30, 'currency' => 'USD']);
    $newPlan = Plan::factory()->create(['amount_minor' => 200, 'duration_days' => 30, 'currency' => 'USD']);
    $oldPlan->prices()->create(['currency' => 'USD', 'amount_minor' => 100, 'effective_from' => now()->subDay()]);
    $newPlan->prices()->create(['currency' => 'USD', 'amount_minor' => 200, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->for($customer)->for($oldPlan)->create([
        'status' => ServiceStatus::Active,
        'activated_at' => now()->subDays(29),
        'expires_at' => now()->addDay(),
    ]);

    app(ChangeServicePlan::class)->handle($service, $newPlan, 'next_cycle');
    expect($service->refresh()->plan_id)->toBe($oldPlan->id)
        ->and($service->metadata['pending_plan_change']['plan_id'])->toBe($newPlan->id)
        ->and(NetworkCommand::count())->toBe(0);

    app(RenewService::class)->handle($service);

    expect($service->refresh()->plan_id)->toBe($newPlan->id)
        ->and($service->metadata)->not->toHaveKey('pending_plan_change')
        ->and(NetworkCommand::query()->where('action', 'change_plan')->count())->toBe(1)
        ->and(ServiceEvent::query()->where('event_type', 'plan_changed')->count())->toBe(1)
        ->and(ServiceEvent::query()->where('event_type', 'plan_change_scheduled')->count())->toBe(1);
});

it('cancels a scheduled plan change without changing the current plan', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $oldPlan = Plan::factory()->create(['currency' => 'USD']);
    $newPlan = Plan::factory()->create(['currency' => 'USD']);
    $service = Service::factory()->for($customer)->for($oldPlan)->create(['status' => ServiceStatus::Active]);
    app(ChangeServicePlan::class)->handle($service, $newPlan, 'next_cycle');

    app(CancelServicePlanChange::class)->handle($service);

    expect($service->refresh()->plan_id)->toBe($oldPlan->id)
        ->and($service->metadata)->not->toHaveKey('pending_plan_change')
        ->and(ServiceEvent::query()->where('event_type', 'plan_change_cancelled')->count())->toBe(1);
});
