<?php

use App\Actions\CreateInvoice;
use App\Actions\CreatePartner;
use App\Actions\GetDashboardAttentionQueue;
use App\Actions\IssueInvoice;
use App\Enums\NetworkState;
use App\Enums\PaymentStatus;
use App\Enums\ServiceStatus;
use App\Models\CurrentSession;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns actionable manager attention rows for the current tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);

    $expired = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => now()->subDay()]);

    $failed = Service::factory()->create(['status' => ServiceStatus::Active, 'network_state' => NetworkState::Failed]);
    $failed->plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(CreateInvoice::class)->handle($failed->customer, $failed->plan, $failed);
    app(IssueInvoice::class)->handle($invoice);
    $paid = Payment::create(['number' => 'PAY-PAID', 'customer_id' => $failed->customer_id, 'invoice_id' => $invoice->id, 'status' => PaymentStatus::Posted, 'amount' => 3500, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'attention-paid-001', 'received_at' => now()]);
    $paid->allocations()->create(['invoice_id' => $invoice->id, 'amount' => 3500, 'currency' => 'USD']);

    $unallocated = Payment::create(['number' => 'PAY-UNALLOCATED', 'customer_id' => $expired->customer_id, 'status' => PaymentStatus::Posted, 'amount' => 1200, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'attention-unallocated-001', 'received_at' => now()]);
    $stale = Service::factory()->create();
    CurrentSession::create(['service_id' => $stale->id, 'username' => $stale->username, 'acct_session_id' => 'acct-attention-001', 'nasname' => 'router-01', 'last_seen_at' => now()->subMinutes(20)]);
    app(CreatePartner::class)->handle('Lowline', 'LOW-001', 'USD', lowBalanceThreshold: 1000);

    $rows = app(GetDashboardAttentionQueue::class)->handle();
    $types = collect($rows)->pluck('type');

    expect($types)->toContain('expired_service', 'paid_provisioning_failed', 'unallocated_payment', 'stale_session', 'low_reseller_balance')
        ->and(collect($rows)->firstWhere('type', 'expired_service')['href'])->toContain('/services?search='.urlencode($expired->username))
        ->and(collect($rows)->firstWhere('type', 'unallocated_payment')['href'])->toContain('/customers/'.$unallocated->customer->public_id);
});
