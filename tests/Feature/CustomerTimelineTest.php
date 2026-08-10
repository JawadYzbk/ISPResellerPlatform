<?php

use App\Actions\CreateInvoice;
use App\Actions\GetCustomerDetails;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns safe customer activity for the staff show screen', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['latitude' => 33.8938, 'longitude' => 35.5018]);
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'timeline-payment-001', $invoice);
    Ticket::create(['customer_id' => $customer->id, 'number' => 'TCK-0001', 'subject' => 'Connection question', 'description' => 'Customer needs help.', 'priority' => 'normal', 'status' => 'open']);

    $details = app(GetCustomerDetails::class)->handle($customer->refresh());

    expect($details['payments'])->toHaveCount(1)
        ->and($details['invoices'])->toHaveCount(1)
        ->and($details['tickets'])->toHaveCount(1)
        ->and($details['tickets'][0]['number'])->toBe('TCK-0001')
        ->and($details['timeline'])->toHaveCount(3)
        ->and(collect($details['timeline'])->pluck('type')->all())->toContain('payment')
        ->and(collect($details['timeline'])->pluck('type')->all())->toContain('invoice')
        ->and($details['timeline'][0])->not->toHaveKey('tenant_id');
    expect((float) $details['latitude'])->toBe(33.8938)
        ->and((float) $details['longitude'])->toBe(35.5018);
});
