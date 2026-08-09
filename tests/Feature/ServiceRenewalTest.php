<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Actions\RenewService;
use App\Actions\SuspendOverdueServices;
use App\Enums\ServiceStatus;
use App\Jobs\ExecuteNetworkCommand;
use App\Models\Customer;
use App\Models\NetworkCommand;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('suspends overdue services idempotently and queues the network action', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => now()->subDay()]);

    expect(app(SuspendOverdueServices::class)->handle($tenant))->toBe(1)
        ->and(app(SuspendOverdueServices::class)->handle($tenant))->toBe(0)
        ->and($service->refresh()->status)->toBe(ServiceStatus::Suspended)
        ->and($service->suspension_reason)->toBe('auto_overdue')
        ->and(NetworkCommand::where('action', 'suspend')->count())->toBe(1);

    Queue::assertPushed(ExecuteNetworkCommand::class);
});

it('renews an auto-overdue service and queues reactivation after payment', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['duration_days' => 30, 'amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->for($customer)->for($plan)->create(['status' => ServiceStatus::Suspended, 'suspension_reason' => 'auto_overdue', 'expires_at' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan, $service));

    app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'renewal-001', $invoice);

    expect($service->refresh()->status)->toBe(ServiceStatus::Active)
        ->and($service->expires_at->isFuture())->toBeTrue()
        ->and(NetworkCommand::where('action', 'activate')->count())->toBe(1);

    Queue::assertPushed(ExecuteNetworkCommand::class);
});

it('extends a manually suspended service without reactivating it', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['status' => ServiceStatus::Suspended, 'suspension_reason' => 'manual', 'expires_at' => now()->subDay()]);

    $updated = app(RenewService::class)->handle($service);

    expect($updated->status)->toBe(ServiceStatus::Suspended)
        ->and($updated->suspension_reason)->toBe('manual')
        ->and($updated->expires_at->isFuture())->toBeTrue()
        ->and(NetworkCommand::count())->toBe(0);
});
