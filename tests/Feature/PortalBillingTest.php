<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\RequestPortalOtp;
use App\Actions\VerifyPortalOtp;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns only the authenticated portal customer billing history', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456789']);
    $plan = Plan::factory()->create();
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $result = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $result['challenge']->id, $result['code']);

    $this->withToken($session['token'])->getJson('/api/v1/portal/northline/billing')
        ->assertOk()
        ->assertJsonPath('invoices.0.id', $invoice->public_id)
        ->assertJsonCount(0, 'payments');
});
