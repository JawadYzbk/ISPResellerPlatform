<?php

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\BillingRun;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function bulkBillingOperator(Tenant $tenant): User
{
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Billing manager',
        'email' => 'bulk-billing-'.$tenant->id.'@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    return $user;
}

it('previews due services and records a row-level bulk renewal outcome', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $operator = bulkBillingOperator($tenant);
    $readyPlan = Plan::factory()->create(['name' => 'Ready plan']);
    $readyPlan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $readyService = Service::factory()->create([
        'plan_id' => $readyPlan->id,
        'status' => ServiceStatus::Active,
        'expires_at' => now()->subDays(2),
        'username' => 'ready-customer',
    ]);
    $blockedService = Service::factory()->create([
        'status' => ServiceStatus::Active,
        'expires_at' => now()->subDay(),
        'username' => 'blocked-customer',
    ]);

    $this->actingAs($operator)
        ->get(route('billing.bulk-renewals'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/BulkRenewals')
            ->where('summary.total', 2)
            ->where('summary.ready', 1)
            ->where('summary.blocked', 1)
            ->where('rows.0.service_id', $readyService->public_id)
            ->where('rows.0.status', 'ready')
            ->where('rows.1.service_id', $blockedService->public_id)
            ->where('rows.1.status', 'blocked')
        );
});

it('processes bulk renewals idempotently and keeps failed rows retryable', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $operator = bulkBillingOperator($tenant);
    $readyPlan = Plan::factory()->create(['name' => 'Ready plan']);
    $readyPlan->prices()->create(['currency' => 'USD', 'amount_minor' => 2500, 'effective_from' => now()->subDay()]);
    $readyService = Service::factory()->create([
        'plan_id' => $readyPlan->id,
        'status' => ServiceStatus::Active,
        'expires_at' => now()->subDay(),
        'username' => 'ready-service',
    ]);
    $blockedService = Service::factory()->create([
        'status' => ServiceStatus::Active,
        'expires_at' => now()->subDay(),
        'username' => 'blocked-service',
    ]);
    $idempotencyKey = '0198d9a4-0e80-72bb-9ef8-44a7bf6c2200';
    $selected = [$blockedService->public_id, $readyService->public_id];

    $this->actingAs($operator)
        ->post(route('billing.bulk-renewals.store'), [
            'service_ids' => $selected,
            'idempotency_key' => $idempotencyKey,
        ])
        ->assertRedirect(route('billing.bulk-renewals'))
        ->assertSessionHas('success_title', 'Billing completed with errors');

    app(Tenancy::class)->set($tenant);
    $run = BillingRun::query()->where('run_type', 'bulk_renewal')->firstOrFail();
    $failedRow = collect($run->metadata['rows'])->firstWhere('status', 'failed');
    expect($run->processed_count)->toBe(1)
        ->and($run->failed_count)->toBe(1)
        ->and($failedRow['service_id'])->toBe($blockedService->public_id)
        ->and(Invoice::count())->toBe(1)
        ->and(Invoice::firstOrFail()->status)->toBe(InvoiceStatus::Issued);

    $this->actingAs($operator)
        ->post(route('billing.bulk-renewals.store'), [
            'service_ids' => [$blockedService->public_id],
            'idempotency_key' => $idempotencyKey,
        ])
        ->assertRedirect(route('billing.bulk-renewals'));

    app(Tenancy::class)->set($tenant);
    expect(BillingRun::count())->toBe(1)
        ->and(Invoice::count())->toBe(1)
        ->and($run->refresh()->processed_count)->toBe(1)
        ->and($run->failed_count)->toBe(1);
});
