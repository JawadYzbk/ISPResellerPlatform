<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\QueueCustomerNotification;
use App\Actions\RecordPayment;
use App\Actions\SuspendOverdueServices;
use App\Enums\ServiceStatus;
use App\Jobs\DeliverMessage;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('selects an available channel and respects notification opt-out', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['notification_preferences' => ['channels' => ['email']]]);
    MessageTemplate::create(['key' => 'payment.receipt', 'channel' => 'email', 'locale' => 'en', 'body' => 'Receipt {{ receipt_number }}']);

    $message = app(QueueCustomerNotification::class)->handle($customer, 'payment.receipt', 'receipt-001', ['receipt_number' => 'RCT-001']);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message?->channel)->toBe('email')
        ->and($message?->recipient)->toBe($customer->email);

    $customer->forceFill(['notification_preferences' => ['payment_receipts' => false]])->save();
    expect(app(QueueCustomerNotification::class)->handle($customer, 'payment.receipt', 'receipt-002'))->toBeNull()
        ->and(Message::query()->count())->toBe(1);
});

it('queues a payment receipt after the payment is posted', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['name' => 'Home 50', 'slug' => 'home-50', 'download_kbps' => 50_000, 'upload_kbps' => 10_000, 'duration_days' => 30, 'amount_minor' => 3500, 'currency' => 'USD']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    MessageTemplate::create(['key' => 'payment.receipt', 'channel' => 'sms', 'locale' => 'en', 'body' => 'Receipt {{ receipt_number }} for {{ amount }} {{ currency }}']);

    $payment = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'notification-payment-001', $invoice);

    expect(Message::query()->where('template_key', 'payment.receipt')->firstOrFail()->body)
        ->toContain($payment->number)
        ->and(Message::query()->where('template_key', 'payment.receipt')->count())->toBe(1);
    Queue::assertPushed(DeliverMessage::class);
});

it('queues suspension and reactivation notices exactly once across the overdue renewal path', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    MessageTemplate::create(['key' => 'service.suspended', 'channel' => 'sms', 'locale' => 'en', 'body' => 'Suspended {{ service_username }}']);
    MessageTemplate::create(['key' => 'service.reactivated', 'channel' => 'sms', 'locale' => 'en', 'body' => 'Reactivated {{ service_username }}']);
    $service = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => now()->subMinute()]);
    $service->plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);

    app(SuspendOverdueServices::class)->handle($tenant);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($service->customer, $service->plan, $service));
    app(RecordPayment::class)->handle($service->customer, 3500, 'USD', 'cash', 'notification-renewal-001', $invoice);

    expect(Message::query()->where('template_key', 'service.suspended')->count())->toBe(1)
        ->and(Message::query()->where('template_key', 'service.reactivated')->count())->toBe(1)
        ->and(Message::query()->where('idempotency_key', 'like', 'service-status:'.$service->id.':%')->count())->toBe(2);
});
